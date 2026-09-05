<?php
/**
 * Fires inside Apps\Core_Subscriptions\Service\Purchase\Process::update()
 * (PF.Site/Apps/core-subscriptions/Service/Purchase/Process.php), an
 * official phpFox extension point - Phpfox_Plugin::get('subscribe.
 * service_purchase_process_update_pre_log') + eval(), the same mechanism
 * every other hooks/ file in this app already uses. No core/native file
 * touched to add this.
 *
 * The problem: when a purchase transitions to 'completed', native update()
 * unconditionally cancels every OTHER completed purchase the same user
 * holds (any package, same or different tier) a few lines above this hook
 * point - status flipped straight to 'cancel' in the database, a
 * subscribe_cancel_reason row inserted, a 'cancel' history row logged.
 * That's correct default phpFox behavior for a classic one-membership-
 * tier site, but wrong for Hulahoot: a Founding Industry Partner must be
 * able to hold different plans, packages, and industries simultaneously
 * (see PurchaseFlow.php's own docblock - it already works around this for
 * purchases IT completes directly, but a purchase completed for real
 * through a payment gateway goes through this native method with no hook
 * Hulahoot controlled, until now).
 *
 * The fix: this hook fires right after that cancel loop, still inside the
 * same update() call, still eval()'d in its local scope - so
 * $aCurrentActiveSubscriptions (the exact array of sp.* rows update() just
 * cancelled, only ever populated in the 'completed' case) and $iPackageId
 * (the package that just completed) are both directly available. For
 * every cancelled row, if BOTH the newly-completed package and the
 * cancelled package are Hulahoot-managed (a row exists in
 * hulahoot_subscription_package for that package_id), the wrongful cancel
 * is undone immediately - before the buyer or anyone else can observe the
 * wrong state outside this one request. A purchase against any package
 * NOT managed by Hulahoot is left exactly as native code just left it -
 * this hook only ever adds a restore, never removes native's own cancel
 * behavior for a plain, non-Hulahoot subscription.
 *
 * Idempotent by construction, not by a separate "already handled" flag:
 * each cancelled row is only acted on if its CURRENT status is still
 * 'cancel' at the moment this runs. Re-running this hook for the same
 * event (or being reached again some other way) finds the row already
 * 'completed' from the first pass and does nothing further - no duplicate
 * restore, no duplicate SWESS sync (Service\Swess::syncPackageEntitlement()
 * is itself idempotent - see its own docblock), no duplicate total_active
 * adjustment (recomputed as a fresh COUNT(*), not incremented).
 *
 * Wrapped defensively: the purchase actually completing right now (real
 * money, in the 'completed' case above) must never fail because a restore
 * for some OTHER purchase hit an unexpected error - every failure here is
 * caught, logged, and swallowed, never allowed to propagate back into
 * update() and break the transition that's actually in progress.
 */
if (!defined('PHPFOX_INSTALLER') && isset($sStatus) && $sStatus === 'completed' && !empty($aCurrentActiveSubscriptions) && isset($iPackageId)) {
    try {
        $bNewPackageIsHulahoot = (bool)db()->select('package_id')
            ->from(':hulahoot_subscription_package')
            ->where(['package_id' => (int)$iPackageId])
            ->execute('getSlaveField');

        if ($bNewPackageIsHulahoot) {
            foreach ($aCurrentActiveSubscriptions as $aCancelledRow) {
                $iCancelledPurchaseId = (int)($aCancelledRow['purchase_id'] ?? 0);
                $iCancelledPackageId = (int)($aCancelledRow['package_id'] ?? 0);
                $iCancelledUserId = (int)($aCancelledRow['user_id'] ?? 0);

                if (!$iCancelledPurchaseId || !$iCancelledPackageId || !$iCancelledUserId) {
                    continue;
                }

                try {
                    $bCancelledPackageIsHulahoot = (bool)db()->select('package_id')
                        ->from(':hulahoot_subscription_package')
                        ->where(['package_id' => $iCancelledPackageId])
                        ->execute('getSlaveField');

                    if (!$bCancelledPackageIsHulahoot) {
                        // Not a Hulahoot package on the cancelled side - leave
                        // native's own cancel exactly as it is (Test C).
                        continue;
                    }

                    // Idempotency guard: only restore a row that's ACTUALLY
                    // sitting as 'cancel' right now. Prevents a second run
                    // (this same event re-reached some other way) from
                    // re-doing work, and prevents ever touching a purchase
                    // that was genuinely, separately cancelled by the user
                    // or an admin through the real 'cancel' transition
                    // (that case never populates $aCurrentActiveSubscriptions
                    // at all, but this guard is cheap insurance regardless).
                    $aCurrentRow = db()->select('status')
                        ->from(':subscribe_purchase')
                        ->where(['purchase_id' => $iCancelledPurchaseId])
                        ->execute('getSlaveRow');

                    if (!$aCurrentRow || $aCurrentRow['status'] !== 'cancel') {
                        continue;
                    }

                    db()->update(':subscribe_purchase', [
                        'status' => 'completed',
                    ], ['purchase_id' => $iCancelledPurchaseId]);

                    db()->delete(':subscribe_cancel_reason', ['purchase_id' => $iCancelledPurchaseId]);

                    db()->updateCount(
                        'subscribe_purchase',
                        'package_id = ' . $iCancelledPackageId . ' AND status = "completed"',
                        'total_active',
                        'subscribe_package',
                        'package_id = ' . $iCancelledPackageId
                    );

                    try {
                        (new \Apps\Hulahoot\Service\Swess())->syncPackageEntitlement($iCancelledUserId);
                    } catch (\Throwable $eSwess) {
                        Phpfox::getLog('hulahoot.log')->error(
                            'Hulahoot multi-package safety hook: restored purchase ' . $iCancelledPurchaseId
                            . ' but SWESS re-sync for user ' . $iCancelledUserId . ' failed: ' . $eSwess->getMessage()
                        );
                    }

                    Phpfox::getLog('hulahoot.log')->info(
                        'Hulahoot multi-package safety hook: purchase ' . $iCancelledPurchaseId
                        . ' (package ' . $iCancelledPackageId . ', user ' . $iCancelledUserId . ') was wrongly'
                        . ' auto-cancelled by native Purchase\\Process::update() when purchase '
                        . (int)($iPurchaseId ?? 0) . ' (package ' . (int)$iPackageId . ') completed for the'
                        . ' same user - restored to completed, cancel reason removed, total_active'
                        . ' recalculated, SWESS entitlement re-synced.'
                    );
                } catch (\Throwable $eRow) {
                    Phpfox::getLog('hulahoot.log')->error(
                        'Hulahoot multi-package safety hook: failed to restore purchase '
                        . $iCancelledPurchaseId . ' after it was auto-cancelled by purchase '
                        . (int)($iPurchaseId ?? 0) . ' completing: ' . $eRow->getMessage()
                    );
                }
            }
        }
    } catch (\Throwable $e) {
        Phpfox::getLog('hulahoot.log')->error(
            'Hulahoot multi-package safety hook: unexpected failure while checking purchase '
            . (int)($iPurchaseId ?? 0) . ' for wrongly auto-cancelled Hulahoot purchases: ' . $e->getMessage()
        );
    }
}

/**
 * Buy Out expansion trigger (separate concern from the restore logic
 * above - this runs regardless of whether $aCurrentActiveSubscriptions
 * was empty, e.g. a buyer's very first purchase). If the purchase that
 * JUST completed is a Hulahoot "Buy Out Remaining Slots" aggregated
 * anchor (hulahoot_purchase_buyout - see Service\PurchaseFlow::
 * buyOutRemainingSlots()'s own docblock), this is the earliest possible
 * moment to expand it into its full slot_count of completed purchase
 * rows - synchronously, inside the very same webhook request that
 * confirmed payment, rather than waiting for the buyer's next page load.
 *
 * $bAssumeCompleted=true is passed deliberately: at this exact point,
 * native update() hasn't yet written $iPurchaseId's own 'completed'
 * status to the database (that write happens a few lines AFTER this
 * hook point) - $sStatus is what's about to be written, so
 * expandCompletedBuyout() is told to trust it rather than re-reading a
 * still-stale row. See that method's own docblock for the full
 * resumable/idempotent expansion design, and start.php's /industry and
 * /find-your-industry routes for the lazy safety-net sweep that finishes
 * the job later if this synchronous attempt never ran or was
 * interrupted (a crash, a timeout, anything) - expandCompletedBuyout()
 * itself guarantees neither path can ever create duplicate or excess
 * rows, however many times or wherever it's invoked.
 */
if (!defined('PHPFOX_INSTALLER') && isset($sStatus) && $sStatus === 'completed' && isset($iPurchaseId)) {
    try {
        (new \Apps\Hulahoot\Service\PurchaseFlow())->expandCompletedBuyout((int)$iPurchaseId, true);
    } catch (\Throwable $eExpand) {
        Phpfox::getLog('hulahoot.log')->error(
            'Hulahoot buy-out expansion trigger: failed for purchase ' . (int)$iPurchaseId . ': ' . $eExpand->getMessage()
        );
    }
}
