<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class PurchaseFlow
 *
 * Initiates a subscription purchase from the Industry package cards,
 * calling the native Core Subscriptions purchase services directly
 * (Service\Purchase\Process::add()/update() - the exact same calls
 * Apps\Core_Subscriptions\Block\UpgradeBlock makes) rather than
 * reimplementing any billing logic. Exists only to avoid one thing
 * UpgradeBlock does that's wrong for Hulahoot's packages specifically:
 * it hard-blocks with "attempting_to_upgrade_to_the_same_user_group_you_are_already_in"
 * whenever the buyer's current group already equals the package's
 * configured user_group_id - and since every Hulahoot package is
 * configured to grant group 2 (Registered Member, chosen specifically as
 * a no-op for an ordinary customer - see seed-demo-data.php), that guard
 * fires for essentially every real customer on every package. Native
 * Core Subscriptions has no concept of "grant no group at all", so this
 * class always passes the buyer's own current group back in as the
 * target - a genuine no-op regardless of what group they started in,
 * matching the explicit decision that purchasing a business package must
 * never change the buyer's phpFox account group.
 *
 * Paid packages are hard-handed to the native gateway-selection page
 * (subscribe.register, Apps\Core_Subscriptions\Controller\RegisterController)
 * once the purchase row exists - confirmed by reading that controller
 * directly that it has no equivalent group-collision guard - so gateway
 * selection, payment processing, and completion all stay entirely
 * native from that point on.
 *
 * Before doing that hand-off, a paid purchase also checks that at least
 * one payment gateway (api_gateway.is_active = 1 - the same row set
 * Apps\Core\Block\Gateway\Form reads via the api.gateway service) is
 * actually active. With zero active gateways the native gateway-
 * selection page has nothing to render - just an empty page - and a
 * customer landing there has no way forward except wandering into
 * native Core Subscriptions pages Hulahoot never wants them to see
 * (confirmed live: no gateway was active, and a customer ended up on
 * the raw native "Membership Packages" browse page). Failing here
 * instead sends them back to the Industry page with a clear message,
 * before a purchase row (or worse, a stray pending one) is even
 * created.
 *
 * One exception: an admin buying with no gateway active gets the
 * purchase completed immediately instead, the same no-op-group
 * completion a free package gets - so staff can preview what a
 * customer sees after a purchase (SWESS Wallet, etc.) today, without
 * waiting on a real gateway to go live. An ordinary member always gets
 * the real block above, gateway or not.
 *
 * Completion deliberately does NOT call native Purchase\Process::update()
 * for the 'completed' transition - that method unconditionally cancels
 * every OTHER completed purchase the same user holds (any package, same
 * or different tier) the moment one purchase completes, as a side effect
 * of its own bookkeeping. That's correct for a classic "one membership
 * tier at a time" site, but wrong here: a Founding Industry Partner can
 * hold more than one package (repeat-buying the same tier, holding two
 * different tiers, or buying out several remaining slots at once all
 * need to coexist as separate completed purchases). completeAsHulahoot()
 * below replicates update()'s other useful effects - account group
 * update (a no-op, see class docblock), purchase history logging, the
 * package's total_active counter, and the confirmation email - while
 * skipping only the auto-cancel step. See Marketplace::getOccupiedSlotCount()
 * for how a package's remaining slots are actually counted, independent
 * of whether the user holds one purchase or several.
 *
 * @package Apps\Hulahoot\Service
 */
class PurchaseFlow
{
    /**
     * @param int $iUserId
     * @param int $iPackageId
     *
     * @return array{completed: bool, purchase_id: int} completed is true
     *         for a free package, or a paid one an admin bypassed with no
     *         gateway active; false means the caller must still hand the
     *         buyer to subscribe.register for gateway selection.
     *
     * @throws \InvalidArgumentException if the package doesn't exist,
     *         isn't currently purchasable, has no slots left, or (for a
     *         paid package) no payment gateway is active yet
     */
    public function initiate($iUserId, $iPackageId)
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            throw new \InvalidArgumentException(_p('hulahoot_subscriptions_app_inactive'));
        }

        $aPackage = Phpfox::getService('subscribe')->getPackage((int)$iPackageId, true);

        if (!$aPackage || !$aPackage['is_active'] || !empty($aPackage['is_removed'])) {
            throw new \InvalidArgumentException(_p('hulahoot_subscription_package_not_found'));
        }

        // Hulahoot's own kill switch (hulahoot_subscription_package.is_active)
        // is independent of the native package's own is_active - an admin
        // can deactivate a package on the Hulahoot side while the native
        // row stays active underneath (see that table's own docblock).
        // Marketplace::getPackagesForIndustry() already hides such a
        // package from the storefront, but without this check here, a
        // direct POST to /industry/subscribe with its package_id would
        // still complete a purchase - the storefront hiding it is not a
        // real access control by itself.
        $aRules = db()->select('purchase_limit')
            ->from(':hulahoot_subscription_package')
            ->where(['package_id' => (int)$iPackageId, 'is_active' => 1])
            ->execute('getSlaveRow');

        if (!$aRules) {
            throw new \InvalidArgumentException(_p('hulahoot_subscription_package_not_found'));
        }

        // Named lock scoped to this one package - serializes every
        // concurrent purchase attempt (single buys and Buy Out alike)
        // against the exact same package_id, closing the check-then-insert
        // race that a plain "count existing rows, then insert" would
        // otherwise have: two simultaneous requests could both read the
        // same "2 of 3 taken" count and both proceed, overselling by one.
        // Scoped per package_id (not global) so purchases of DIFFERENT
        // packages never block each other. 10s wait is generous for how
        // fast this critical section actually runs (a handful of small
        // writes, no network calls) - a real wait here would only ever
        // happen under a genuine burst of concurrent buyers on the same
        // package, which is exactly the case this exists to protect.
        $sLockName = 'hulahoot_purchase_pkg_' . (int)$iPackageId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 10) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            throw new \InvalidArgumentException(_p('hulahoot_purchase_busy'));
        }

        try {
            $this->assertSlotAvailable((int)$iPackageId);

            $bFree = ((float)$aPackage['default_cost'] === 0.0);
            $bHasGateway = $this->hasActiveGateway();

            if (!$bFree && !$bHasGateway && !Phpfox::isAdmin()) {
                throw new \InvalidArgumentException(_p('hulahoot_no_payment_gateway_active'));
            }

            $aUser = db()->select('user_group_id')
                ->from(':user')
                ->where(['user_id' => (int)$iUserId])
                ->execute('getSlaveRow');
            $iCurrentGroupId = (int)($aUser['user_group_id'] ?? 0);

            $iPurchaseId = Phpfox::getService('subscribe.purchase.process')->add([
                'package_id' => (int)$iPackageId,
                'currency_id' => $aPackage['default_currency_id'],
                'price' => $aPackage['default_cost'],
                'renew_type' => 0,
            ], (int)$iUserId);

            if ($bFree || (!$bHasGateway && Phpfox::isAdmin())) {
                $this->completeAsHulahoot($iPurchaseId, (int)$iPackageId, (int)$iUserId, $iCurrentGroupId, $aPackage);

                return ['completed' => true, 'purchase_id' => $iPurchaseId];
            }

            // Paid: leave status as-is (pending) and hand off to the native
            // gateway-selection page - same bookkeeping call UpgradeBlock
            // makes before doing that handoff itself.
            Phpfox::getService('subscribe.purchase.process')->changePurchaseForSigningUp($iPurchaseId, (int)$iUserId);

            return ['completed' => false, 'purchase_id' => $iPurchaseId];
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * Buys every remaining slot of a limited package in one go, all
     * under the same buyer. Only meaningful for a package with a real
     * purchase_limit; refuses an unlimited package (nothing to "buy
     * out") or one already at zero.
     *
     * Free package: unchanged from before - a loop calling initiate(),
     * each slot its own independent completed purchase, each call
     * managing its own per-package lock and fresh availability check.
     *
     * Paid package: ONE real purchase row is created, priced at
     * slot_count x the package's unit cost, and handed off to the native
     * gateway-selection page exactly like a normal single paid purchase -
     * except hooks/subscribe.component_controller_register__1.php (no
     * native file touched) overrides the checkout amount to match that
     * total, so the buyer is charged correctly for every slot in one
     * payment. hulahoot_purchase_buyout records that this purchase_id
     * represents slot_count slots. Once the real gateway confirms it
     * paid (native Callback.php, unmodified), expandCompletedBuyout()
     * creates the remaining (slot_count - 1) completed purchase rows
     * directly - never through native Purchase\Process::update(), which
     * would auto-cancel the buyer's other completed purchases (the exact
     * problem hooks/subscribe.service_purchase_process_update_pre_log.php
     * already prevents for existing purchases; expansion avoids ever
     * triggering it again for the new ones). See that hook and
     * expandCompletedBuyout()'s own docblock for the full resumable/
     * idempotent expansion design.
     *
     * @param int $iUserId
     * @param int $iPackageId
     *
     * @return array{completed: bool, completed_count?: int, purchase_ids?: int[], purchase_id?: int, slot_count?: int}
     *         completed=true (free path) carries completed_count/purchase_ids;
     *         completed=false (paid path) carries purchase_id/slot_count and
     *         means the caller must hand the buyer to subscribe.register.
     *
     * @throws \InvalidArgumentException if the package doesn't exist,
     *         is unlimited, has zero slots left, is paid with no payment
     *         gateway active, or (paid only) another purchase/buy-out for
     *         the same package is in progress right now
     */
    public function buyOutRemainingSlots($iUserId, $iPackageId)
    {
        $iPackageId = (int)$iPackageId;
        $iUserId = (int)$iUserId;

        $aPackage = Phpfox::getService('subscribe')->getPackage($iPackageId, true);

        if (!$aPackage || !$aPackage['is_active'] || !empty($aPackage['is_removed'])) {
            throw new \InvalidArgumentException(_p('hulahoot_subscription_package_not_found'));
        }

        $aRules = db()->select('purchase_limit')
            ->from(':hulahoot_subscription_package')
            ->where(['package_id' => $iPackageId, 'is_active' => 1])
            ->execute('getSlaveRow');

        if (!$aRules) {
            throw new \InvalidArgumentException(_p('hulahoot_subscription_package_not_found'));
        }

        if ($aRules['purchase_limit'] === null) {
            throw new \InvalidArgumentException(_p('hulahoot_package_not_limited'));
        }

        $bFree = ((float)$aPackage['default_cost'] === 0.0);

        if ($bFree) {
            $iRemaining = max(0, (int)$aRules['purchase_limit'] - (new Marketplace())->getOccupiedSlotCount($iPackageId));

            if ($iRemaining < 1) {
                throw new \InvalidArgumentException(_p('hulahoot_package_sold_out'));
            }

            // Each initiate() call re-checks availability fresh under its
            // own per-package lock, so a concurrent purchase racing this
            // loop (another buyer, or another Buy Out) can legitimately
            // make a later iteration here fail with "sold out" even though
            // $iRemaining looked fine when this loop started - that's
            // correct, not a bug (it's exactly what stops this loop from
            // overselling). Catching it here means a buyer who asked for 5
            // and got 3 before someone else took the rest still sees "you
            // got 3", not a bare error that hides the 3 purchases that
            // already went through.
            $aPurchaseIds = [];
            for ($i = 0; $i < $iRemaining; $i++) {
                try {
                    $aResult = $this->initiate($iUserId, $iPackageId);
                    $aPurchaseIds[] = $aResult['purchase_id'];
                } catch (\InvalidArgumentException $e) {
                    break;
                }
            }

            if (!$aPurchaseIds) {
                throw new \InvalidArgumentException(_p('hulahoot_package_sold_out'));
            }

            return ['completed' => true, 'completed_count' => count($aPurchaseIds), 'purchase_ids' => $aPurchaseIds];
        }

        if (!$this->hasActiveGateway()) {
            throw new \InvalidArgumentException(_p('hulahoot_no_payment_gateway_active'));
        }

        // Same named lock initiate() uses, scoped to this one method's
        // own call - never nested with initiate()'s internal locking (the
        // free branch above never reaches here, and this branch never
        // calls initiate() itself), so there's no reentrancy concern.
        $sLockName = 'hulahoot_purchase_pkg_' . $iPackageId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 10) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            throw new \InvalidArgumentException(_p('hulahoot_purchase_busy'));
        }

        try {
            $iRemaining = max(0, (int)$aRules['purchase_limit'] - (new Marketplace())->getOccupiedSlotCount($iPackageId));

            if ($iRemaining < 1) {
                throw new \InvalidArgumentException(_p('hulahoot_package_sold_out'));
            }

            $fTotalPrice = round((float)$aPackage['default_cost'] * $iRemaining, 2);

            $iPurchaseId = Phpfox::getService('subscribe.purchase.process')->add([
                'package_id' => $iPackageId,
                'currency_id' => $aPackage['default_currency_id'],
                'price' => $fTotalPrice,
                'renew_type' => 0,
            ], $iUserId);

            db()->insert(':hulahoot_purchase_buyout', [
                'purchase_id' => $iPurchaseId,
                'user_id' => $iUserId,
                'package_id' => $iPackageId,
                'slot_count' => $iRemaining,
                'is_expanded' => 0,
                'created' => time(),
            ]);

            // Same bookkeeping call initiate()'s own paid path makes before
            // handing off to the native gateway-selection page.
            Phpfox::getService('subscribe.purchase.process')->changePurchaseForSigningUp($iPurchaseId, $iUserId);

            Phpfox::getLog('hulahoot.log')->info(
                'Hulahoot buy-out: created aggregated purchase ' . $iPurchaseId . ' for user ' . $iUserId
                . ', package ' . $iPackageId . ' - ' . $iRemaining . ' slot(s), total ' . $fTotalPrice
                . ' ' . $aPackage['default_currency_id'] . '. Awaiting real gateway checkout.'
            );

            return ['completed' => false, 'purchase_id' => $iPurchaseId, 'slot_count' => $iRemaining];
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * Finishes expanding ONE aggregated Buy Out purchase into the full
     * slot_count of completed purchase rows it was paid for, once the
     * real gateway has confirmed it as completed. Safe to call for a
     * purchase_id that isn't a Buy Out anchor at all (no-op), one that's
     * still pending payment (no-op, unless $bAssumeCompleted), or one
     * that's already fully expanded (no-op) - callers never need to
     * check any of that first.
     *
     * Resumable by design, not just idempotent: rather than "have I run
     * for this purchase before", it asks "how many of the slot_count - 1
     * rows this buy-out needs already exist" (hulahoot_purchase_buyout_
     * slot - see that table's own docblock) and creates only the
     * remaining deficit. A crash, timeout, or any other interruption
     * partway through a previous attempt is corrected on the very next
     * call, from wherever it left off - never by starting over, so it
     * can never exceed slot_count total rows no matter how many times or
     * where it's interrupted and retried. is_expanded is only set to 1
     * once every row is confirmed created.
     *
     * A per-purchase named lock (distinct from buyOutRemainingSlots()'s
     * own per-PACKAGE lock - this one is per anchor PURCHASE) means the
     * synchronous call from hooks/subscribe.service_purchase_process_
     * update_pre_log.php and a lazy sweep call (see start.php's /industry
     * and /find-your-industry routes) landing at nearly the same moment
     * can never both try to create the same rows at once; the loser
     * simply returns immediately rather than waiting, since the winner
     * finishing shortly after leaves nothing left to do.
     *
     * @param int $iPurchaseId
     * @param bool $bAssumeCompleted True only from the synchronous hook
     *        call, which runs INSIDE native update() before that method
     *        has written this purchase's own 'completed' status to the
     *        database yet - the $sStatus local variable it passes along
     *        is what's about to be written, so re-reading the row here
     *        would see the stale pre-completion status and wrongly no-op.
     *        Every other caller (the lazy sweep) leaves this false and
     *        gets a real, current status check.
     */
    public function expandCompletedBuyout($iPurchaseId, $bAssumeCompleted = false)
    {
        $iPurchaseId = (int)$iPurchaseId;

        $aBuyout = db()->select('*')->from(':hulahoot_purchase_buyout')->where(['purchase_id' => $iPurchaseId])->execute('getSlaveRow');

        if (!$aBuyout || (int)$aBuyout['is_expanded'] === 1) {
            return;
        }

        if (!$bAssumeCompleted) {
            $aRow = db()->select('status')->from(':subscribe_purchase')->where(['purchase_id' => $iPurchaseId])->execute('getSlaveRow');
            if (!$aRow || $aRow['status'] !== 'completed') {
                return;
            }
        }

        $sLockName = 'hulahoot_buyout_expand_' . $iPurchaseId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 10) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            // Another process is already expanding this exact buy-out
            // right now - it will finish; never block this caller's own
            // request waiting on it.
            return;
        }

        try {
            // Re-check under the lock - another process may have finished
            // expanding this buy-out between our first check above and
            // acquiring the lock just now.
            $aFresh = db()->select('is_expanded')->from(':hulahoot_purchase_buyout')->where(['purchase_id' => $iPurchaseId])->execute('getSlaveRow');
            if (!$aFresh || (int)$aFresh['is_expanded'] === 1) {
                return;
            }

            $iAlreadyCreated = (int)db()->select('COUNT(*) c')->from(':hulahoot_purchase_buyout_slot')->where(['buyout_purchase_id' => $iPurchaseId])->execute('getSlaveField');
            $iTargetExtra = max(0, (int)$aBuyout['slot_count'] - 1);
            $iNeeded = $iTargetExtra - $iAlreadyCreated;

            if ($iNeeded < 0) {
                // Should never happen (would mean more expansion rows
                // exist than this buy-out was ever meant to have) - stop
                // and log loudly rather than silently doing anything
                // further to a purchase in a state that shouldn't exist.
                Phpfox::getLog('hulahoot.log')->error(
                    'Hulahoot buy-out expansion: purchase ' . $iPurchaseId . ' has ' . $iAlreadyCreated
                    . ' expansion row(s) already, more than slot_count (' . $aBuyout['slot_count'] . ') - 1 allows. Not creating more; not marking expanded.'
                );

                return;
            }

            $aAnchorPurchase = db()->select('price, currency_id')->from(':subscribe_purchase')->where(['purchase_id' => $iPurchaseId])->execute('getSlaveRow');
            $iSlotCount = max(1, (int)$aBuyout['slot_count']);
            $fUnitPrice = $aAnchorPurchase ? round((float)$aAnchorPurchase['price'] / $iSlotCount, 2) : 0;
            $sCurrencyId = $aAnchorPurchase['currency_id'] ?? 'USD';

            for ($i = 0; $i < $iNeeded; $i++) {
                $iNewPurchaseId = $this->createExpansionSlot((int)$aBuyout['package_id'], (int)$aBuyout['user_id'], $fUnitPrice, $sCurrencyId);

                db()->insert(':hulahoot_purchase_buyout_slot', [
                    'buyout_purchase_id' => $iPurchaseId,
                    'purchase_id' => $iNewPurchaseId,
                    'created' => time(),
                ]);
            }

            db()->updateCount('subscribe_purchase', 'package_id = ' . (int)$aBuyout['package_id'] . ' AND status = "completed"', 'total_active', 'subscribe_package', 'package_id = ' . (int)$aBuyout['package_id']);

            try {
                (new Swess())->syncPackageEntitlement((int)$aBuyout['user_id']);
            } catch (\Throwable $eSwess) {
                Phpfox::getLog('hulahoot.log')->error(
                    'Hulahoot buy-out expansion: purchase ' . $iPurchaseId . ' expanded but SWESS re-sync for user '
                    . $aBuyout['user_id'] . ' failed: ' . $eSwess->getMessage()
                );
            }

            db()->update(':hulahoot_purchase_buyout', ['is_expanded' => 1], ['purchase_id' => $iPurchaseId]);

            Phpfox::getLog('hulahoot.log')->info(
                'Hulahoot buy-out expansion: purchase ' . $iPurchaseId . ' (package ' . $aBuyout['package_id']
                . ', user ' . $aBuyout['user_id'] . ') fully expanded to ' . $aBuyout['slot_count']
                . ' completed slot(s) - ' . $iNeeded . ' new row(s) created this pass, ' . $iAlreadyCreated
                . ' already existed from a prior attempt.'
            );
        } catch (\Throwable $e) {
            Phpfox::getLog('hulahoot.log')->error(
                'Hulahoot buy-out expansion: failed while expanding purchase ' . $iPurchaseId . ': ' . $e->getMessage()
            );
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * Lazy safety-net sweep - the same established pattern as
     * Service\Swess::syncPackageEntitlement()'s own lazy sync (see that
     * method's docblock). Called on every /industry and /find-your-
     * industry page load for the logged-in user (see start.php) so that
     * even if the synchronous expansion attempt inside hooks/subscribe.
     * service_purchase_process_update_pre_log.php never ran or was
     * interrupted (a crash between payment and that hook, a timeout,
     * anything), the very next time this buyer is anywhere in the
     * Hulahoot marketplace, any of their still-unexpanded but genuinely
     * paid buy-outs get finished.
     *
     * @param int $iUserId
     */
    public function expandAllPendingBuyouts($iUserId)
    {
        $aPending = db()->select('purchase_id')
            ->from(':hulahoot_purchase_buyout')
            ->where(['user_id' => (int)$iUserId, 'is_expanded' => 0])
            ->execute('getSlaveRows');

        foreach ($aPending as $aRow) {
            $this->expandCompletedBuyout((int)$aRow['purchase_id']);
        }
    }

    /**
     * Creates ONE completed purchase row directly - never through native
     * Purchase\Process::update() (see expandCompletedBuyout()'s own
     * docblock for why) - representing one slot of an already-paid
     * aggregated Buy Out. $fUnitPrice is the per-slot price (the anchor
     * purchase's own total price divided back down by its slot_count),
     * not the aggregated total - this row should look, to every other
     * part of the system, exactly like an ordinary single-slot purchase
     * of this package.
     *
     * expiry_date is hard-set to 0 ("never expires", native's own
     * marker) rather than copied from the anchor purchase: every
     * Hulahoot package is recurring_period = 0 (see Marketplace.php /
     * PurchaseFlow.php's own docblocks), and native Callback.php
     * deterministically sets expiry_date = 0 for exactly that
     * combination (completed + non-recurring) - but it does so AFTER
     * Purchase\Process::update() returns, which is AFTER the synchronous
     * expansion call inside this class's own hook has already run. The
     * anchor's real expiry_date isn't reliably readable yet at that
     * point; hard-coding the value native code is guaranteed to compute
     * anyway sidesteps that ordering entirely rather than risking a
     * stale read. Marketplace::reconcilePurchaseTermsForUser() later
     * corrects this row's expiry_date=0 to its real term from time_stamp
     * (set below), the exact same lazy fix it applies to the anchor
     * itself - see that method's own docblock.
     *
     * @param int $iPackageId
     * @param int $iUserId
     * @param float $fUnitPrice
     * @param string $sCurrencyId
     *
     * @return int the new purchase_id
     */
    private function createExpansionSlot($iPackageId, $iUserId, $fUnitPrice, $sCurrencyId)
    {
        $iNewPurchaseId = Phpfox::getService('subscribe.purchase.process')->add([
            'package_id' => $iPackageId,
            'currency_id' => $sCurrencyId,
            'price' => $fUnitPrice,
            'renew_type' => 0,
        ], $iUserId);

        $sTransactionId = Phpfox::getService('subscribe.helper')->generateTransactionId();

        db()->update(':subscribe_purchase', [
            'status' => 'completed',
            'time_stamp' => PHPFOX_TIME,
            'transaction_id' => $sTransactionId,
            'expiry_date' => 0,
        ], ['purchase_id' => $iNewPurchaseId]);

        Phpfox::getService('subscribe.purchase.process')->addRecentPurchase([
            'purchase_id' => $iNewPurchaseId,
            'status' => 'completed',
            'time_stamp' => PHPFOX_TIME,
            'currency_id' => $sCurrencyId,
            'payment_method' => 'stripe',
            'transaction_id' => $sTransactionId,
            'total_paid' => $fUnitPrice,
        ]);

        return $iNewPurchaseId;
    }

    /**
     * @param int $iPackageId
     *
     * @throws \InvalidArgumentException if the package has a purchase_limit
     *         and it's already been reached
     */
    private function assertSlotAvailable($iPackageId)
    {
        $aRules = db()->select('purchase_limit')
            ->from(':hulahoot_subscription_package')
            ->where(['package_id' => $iPackageId, 'is_active' => 1])
            ->execute('getSlaveRow');

        if (!$aRules || $aRules['purchase_limit'] === null) {
            return;
        }

        $iOccupied = (new Marketplace())->getOccupiedSlotCount($iPackageId);

        if ($iOccupied >= (int)$aRules['purchase_limit']) {
            throw new \InvalidArgumentException(_p('hulahoot_package_sold_out'));
        }
    }

    /**
     * Finishes a purchase without native update()'s auto-cancel side
     * effect (see class docblock). Sets expiry_date to now +
     * Marketplace::SUBSCRIPTION_TERM_DAYS - native update() never
     * actually computes this for a paid/recurring package on this
     * completion path (confirmed by reading Purchase\Process::update()
     * directly: its expiry_date-setting branch only fires for a package
     * that's both free AND non-recurring), so leaving that to native
     * code would have meant every purchase silently never expired.
     *
     * @param int $iPurchaseId
     * @param int $iPackageId
     * @param int $iUserId
     * @param int $iUserGroupId
     * @param array $aPackage
     */
    private function completeAsHulahoot($iPurchaseId, $iPackageId, $iUserId, $iUserGroupId, array $aPackage)
    {
        Phpfox::getService('user.process')->updateUserGroup($iUserId, $iUserGroupId);

        db()->update(':user_field', ['subscribe_id' => '0'], 'user_id = ' . $iUserId);

        $sTransactionId = Phpfox::getService('subscribe.helper')->generateTransactionId();
        $iExpiryDate = time() + (Marketplace::getSubscriptionTermDays() * 86400);

        Phpfox::getService('subscribe.purchase.process')->addRecentPurchase([
            'purchase_id' => $iPurchaseId,
            'status' => 'completed',
            'time_stamp' => PHPFOX_TIME,
            'currency_id' => $aPackage['default_currency_id'],
            'payment_method' => '',
            'transaction_id' => $sTransactionId,
            'total_paid' => $aPackage['default_cost'],
        ]);

        db()->update(':subscribe_purchase', [
            'status' => 'completed',
            'time_stamp' => PHPFOX_TIME,
            'transaction_id' => $sTransactionId,
            'expiry_date' => $iExpiryDate,
        ], 'purchase_id = ' . (int)$iPurchaseId);

        db()->updateCount('subscribe_purchase', 'package_id = ' . $iPackageId . ' AND status = "completed"', 'total_active', 'subscribe_package', 'package_id = ' . $iPackageId);

        // If this package includes SWESS (hulahoot_subscription_package.
        // swess_enabled), reconcile the buyer's whitelist now rather than
        // waiting for their next SWESS page load - see
        // Swess::syncPackageEntitlement()'s own docblock for the full
        // auto-grant rule (never touches an admin-managed row) and why
        // this same call also happens lazily on every /hulahoot/swess/*
        // route (this completion path is the only one Hulahoot code
        // controls end to end; a real paid-gateway purchase completes
        // entirely inside native Core_Subscriptions with no hook here).
        (new Swess())->syncPackageEntitlement($iUserId);

        // The purchase is already fully committed above (status, expiry,
        // history, counter) by this point - a transient failure sending
        // the confirmation email must never turn into an apparent
        // purchase failure for the buyer. Swallow it here rather than
        // letting it propagate up through initiate()'s try/finally and
        // surface as an error on a purchase that actually succeeded.
        try {
            Phpfox::getLib('mail')
                ->to($iUserId)
                ->subject(['subscribe.membership_successfully_updated_site_title', ['site_title' => Phpfox::getParam('core.site_title')]])
                ->message(['subscribe.your_membership_on_site_title_has_successfully_been_updated', [
                    'site_title' => Phpfox::getParam('core.site_title'),
                    'link' => \Phpfox_Url::instance()->makeUrl('subscribe.view', ['id' => $iPurchaseId]),
                ]])
                ->notification('subscribe.subscribe_notifications')
                ->send();
        } catch (\Throwable $e) {
            // Nothing else to do here - the purchase itself is not at risk.
        }
    }

    /**
     * @return bool
     */
    private function hasActiveGateway()
    {
        return (bool)db()->select('COUNT(*)')
            ->from(':api_gateway')
            ->where(['is_active' => 1])
            ->execute('getSlaveField');
    }
}
