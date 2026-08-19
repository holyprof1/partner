<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class Entitlement
 *
 * What a user is currently allowed to do, derived entirely from their
 * native Core Subscriptions purchase state plus the Hulahoot companion
 * rules on whichever package they bought - no separate "entitlement"
 * table, no duplicated balance/limit columns. An entitlement is a VIEW
 * over subscribe_purchase + hulahoot_subscription_package, not its own
 * stored record: phpFox already tracks purchase status/expiry
 * authoritatively, and Phase 2's own companion tables already track the
 * limits - this class just answers "what does that combination mean for
 * this user right now."
 *
 * This is the intended seam between the Partner Portal and the main
 * Hulahoot application: a promotion can only be created here if
 * getActiveEntitlement() returns non-null, and (once built) publishing a
 * promotion to the main app will decrement the counters this class
 * reads. See docs/HULAHOOT_INTEGRATION.md and Service/HulahootPublisher.php.
 *
 * @package Apps\Hulahoot\Service
 */
class Entitlement
{
    /**
     * The user's current entitlement, or null if they have no completed,
     * unexpired subscription purchase. When multiple completed purchases
     * exist (shouldn't happen under normal use - Core Subscriptions
     * cancels a user's other active subscriptions the moment a new one
     * completes, see Purchase\Process::update()'s 'completed' branch -
     * but is not schema-enforced), the most recently purchased one wins.
     *
     * @param int $iUserId
     *
     * @return array|null {
     *     purchase_id, package_id, package_title (phrase var_name - wrap
     *     in _p()), status, expiry_date,
     *     purchase_limit, campaign_limit, posting_limit_per_day,
     *     posting_limit_per_month, monthly_credits, swess_enabled (all
     *     from the Hulahoot companion row - null limit = unlimited,
     *     matching that table's own convention),
     *     promotions_used, campaigns_used (COUNT()s against whatever
     *     promotion/campaign table Hulahoot eventually creates - both
     *     hardcoded to 0 for now: Phase 2 builds no promotion table yet,
     *     see docs/HULAHOOT_INTEGRATION.md "What Phase 3 adds")
     * }
     */
    public function getActiveEntitlement($iUserId)
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            return null;
        }

        $aPurchase = db()->select('sp.purchase_id, sp.package_id, sp.status, sp.expiry_date, spack.title AS package_title')
            ->from(':subscribe_purchase', 'sp')
            ->join(':subscribe_package', 'spack', 'spack.package_id = sp.package_id')
            ->where(['sp.user_id' => (int)$iUserId, 'sp.status' => 'completed'])
            ->order('sp.time_stamp DESC')
            ->execute('getSlaveRow');

        if (!$aPurchase) {
            return null;
        }

        if (!empty($aPurchase['expiry_date']) && (int)$aPurchase['expiry_date'] < time()) {
            return null;
        }

        $aRules = db()->select('purchase_limit, campaign_limit, posting_limit_per_day, posting_limit_per_month, monthly_credits, swess_enabled')
            ->from(':hulahoot_subscription_package')
            ->where(['package_id' => (int)$aPurchase['package_id'], 'is_active' => 1])
            ->execute('getSlaveRow');

        if (!$aRules) {
            // Package has no active Hulahoot companion row - purchased
            // natively, but Hulahoot hasn't configured (or has disabled)
            // rules for it. No configured limits means no entitlement to
            // create anything here, even though the native purchase
            // itself is perfectly valid - matches SubscriptionPackage's
            // own "no row = not an error, just unconfigured" convention,
            // just applied to the gating decision instead of a display
            // default.
            return null;
        }

        return array_merge($aPurchase, $aRules, [
            'promotions_used' => 0,
            'campaigns_used' => 0,
        ]);
    }

    /**
     * @param int $iUserId
     *
     * @return bool
     */
    public function hasActiveEntitlement($iUserId)
    {
        return $this->getActiveEntitlement($iUserId) !== null;
    }

    /**
     * The specific completed, unexpired, swess_enabled purchase that
     * currently justifies this user's automatic SWESS access - used by
     * Service\Swess::syncPackageEntitlement() for both granting (which
     * package_id to record as the auto-grant's source) and revoking
     * (whether ANY such purchase still exists at all).
     *
     * Deliberately not the same query as getActiveEntitlement(): that
     * method looks only at the user's single MOST RECENT completed
     * purchase (correct for its own purpose - promotion/campaign limits
     * come from exactly one active package at a time) and returns null
     * the moment that one purchase isn't swess_enabled or has expired,
     * even if an OLDER purchase the user still holds is swess_enabled
     * and still perfectly valid. Since Hulahoot purchases now routinely
     * coexist across industries/tiers (see PurchaseFlow.php's own
     * docblock on native auto-cancel), SWESS access must be decided by
     * "does ANY currently-active held purchase qualify," not "does the
     * latest one." Scans every completed purchase, newest first, and
     * returns the first that hasn't expired - same expiry idiom
     * getActiveEntitlement() already uses (empty() on expiry_date, so a
     * still-unreconciled 0 - see Marketplace::reconcilePurchaseTermsForUser() -
     * is correctly treated as not-yet-expired either way).
     *
     * @param int $iUserId
     *
     * @return array|null {purchase_id, package_id, expiry_date}
     */
    public function getActiveSwessEntitlement($iUserId)
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            return null;
        }

        $aPurchases = db()->select('sp.purchase_id, sp.package_id, sp.expiry_date')
            ->from(':subscribe_purchase', 'sp')
            ->join(':hulahoot_subscription_package', 'hsp', 'hsp.package_id = sp.package_id')
            ->where([
                'sp.user_id' => (int)$iUserId,
                'sp.status' => 'completed',
                'hsp.swess_enabled' => 1,
                'hsp.is_active' => 1,
            ])
            ->order('sp.time_stamp DESC')
            ->execute('getSlaveRows');

        $iNow = time();

        foreach ($aPurchases as $aPurchase) {
            if (!empty($aPurchase['expiry_date']) && (int)$aPurchase['expiry_date'] < $iNow) {
                continue;
            }

            return $aPurchase;
        }

        return null;
    }

    /**
     * @param int $iUserId
     *
     * @return bool
     */
    public function hasAnyActiveSwessEntitlement($iUserId)
    {
        return $this->getActiveSwessEntitlement($iUserId) !== null;
    }
}
