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
     * under the same buyer - each slot becomes its own independent
     * completed purchase (same reasoning as completeAsHulahoot()). Only
     * meaningful for a package with a real purchase_limit; refuses an
     * unlimited package (nothing to "buy out") or one already at zero.
     *
     * Only usable for a free package - buying out several PAID slots
     * through a real payment gateway in one checkout would need a
     * per-item checkout step this bulk loop doesn't have, and is a
     * separate, larger feature. Enforced explicitly below (not just
     * documented) since Stripe going active made this a real path a
     * customer could hit, not just a theoretical one.
     *
     * @param int $iUserId
     * @param int $iPackageId
     *
     * @return array{completed_count: int, purchase_ids: int[]}
     *
     * @throws \InvalidArgumentException if the package doesn't exist,
     *         is unlimited, has zero slots left, or (for a paid package)
     *         no payment gateway is active and the buyer isn't an admin
     */
    public function buyOutRemainingSlots($iUserId, $iPackageId)
    {
        $iPackageId = (int)$iPackageId;

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

        // Buy Out only ever completes a purchase immediately - free, or an
        // admin preview with no gateway active (see initiate()). Now that a
        // real gateway (Stripe) is active, a paid package no longer takes
        // that immediate-complete branch for ANYONE, admin included -
        // initiate() instead leaves the purchase pending and expects a
        // single interactive gateway checkout to finish it. This loop has
        // no such per-item checkout step, so left unguarded it would
        // silently create N pending, unpaid purchase rows and still report
        // "success" with a count - a false positive with real money never
        // collected. Block it here with a clear reason instead, matching
        // this method's original documented scope (see class docblock).
        $bFree = ((float)$aPackage['default_cost'] === 0.0);
        if (!$bFree) {
            throw new \InvalidArgumentException(_p('hulahoot_buy_out_paid_not_supported'));
        }

        $iRemaining = max(0, (int)$aRules['purchase_limit'] - (new Marketplace())->getOccupiedSlotCount($iPackageId));

        if ($iRemaining < 1) {
            throw new \InvalidArgumentException(_p('hulahoot_package_sold_out'));
        }

        // Each initiate() call re-checks availability fresh under its own
        // per-package lock, so a concurrent purchase racing this loop
        // (another buyer, or another Buy Out) can legitimately make a
        // later iteration here fail with "sold out" even though $iRemaining
        // looked fine when this loop started - that's correct, not a bug
        // (it's exactly what stops this loop from overselling). Catching
        // it here means a buyer who asked for 5 and got 3 before someone
        // else took the rest still sees "you got 3", not a bare error
        // that hides the 3 purchases that already went through.
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

        return ['completed_count' => count($aPurchaseIds), 'purchase_ids' => $aPurchaseIds];
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
        $iExpiryDate = time() + (Marketplace::SUBSCRIPTION_TERM_DAYS * 86400);

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
