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
     * The user's current entitlement, merged across EVERY completed,
     * unexpired, Hulahoot-managed purchase they hold - not just the most
     * recently purchased one. Confirmed requirement: "joining another
     * plan... doesn't mean that it auto cancels.. it should just add the
     * bonus and subs... if i join 2 industries, the 2 industries benefits
     * will be merged." Purchases across different industries/tiers are
     * expected to coexist (see PurchaseFlow.php's own docblock and
     * hooks/subscribe.service_purchase_process_update_pre_log.php, which
     * already stops native Core Subscriptions from auto-cancelling them) -
     * this method is what makes holding several of them actually additive
     * rather than only the newest one counting.
     *
     * Merge rule per numeric limit (purchase_limit, campaign_limit,
     * posting_limit_per_day, posting_limit_per_month): SUMMED across every
     * active purchase, EXCEPT if any one of them is NULL (unlimited) - a
     * plan that removes the cap entirely can't be capped by also holding a
     * smaller plan, so the merged result is NULL (unlimited) too, same
     * "null = unlimited" convention hulahoot_subscription_package itself
     * uses. monthly_credits is NOT NULL-able (schema default '0') so it's
     * always a plain sum. swess_enabled is true if ANY active purchase
     * grants it - matches getActiveSwessEntitlement()'s own "does ANY
     * held purchase qualify" rule exactly, just folded into this method
     * too instead of only living in that separate one.
     *
     * @param int $iUserId
     *
     * @return array|null {
     *     package_title (already phrase-resolved, comma-joined if more
     *         than one active purchase - do not wrap in _p() again),
     *     purchase_id, package_id, status, expiry_date (the SOONEST of
     *         the merged purchases - the one worth warning about first),
     *     active_purchases: {purchase_id, package_id, package_title,
     *         expiry_date}[] every purchase this merge drew from,
     *     purchase_limit, campaign_limit, posting_limit_per_day,
     *     posting_limit_per_month, monthly_credits, swess_enabled (merged
     *         per the rule above - null limit = unlimited),
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

        $aPurchases = db()->select('sp.purchase_id, sp.package_id, sp.status, sp.expiry_date, spack.title AS package_title, hsp.purchase_limit, hsp.campaign_limit, hsp.posting_limit_per_day, hsp.posting_limit_per_month, hsp.monthly_credits, hsp.swess_enabled')
            ->from(':subscribe_purchase', 'sp')
            ->join(':subscribe_package', 'spack', 'spack.package_id = sp.package_id')
            ->join(':hulahoot_subscription_package', 'hsp', 'hsp.package_id = sp.package_id AND hsp.is_active = 1')
            ->where(['sp.user_id' => (int)$iUserId, 'sp.status' => 'completed'])
            ->order('sp.expiry_date ASC')
            ->execute('getSlaveRows');

        $iNow = time();
        $aActive = [];
        foreach ($aPurchases as $aPurchase) {
            if (!empty($aPurchase['expiry_date']) && (int)$aPurchase['expiry_date'] < $iNow) {
                continue;
            }
            $aActive[] = $aPurchase;
        }

        if (!$aActive) {
            // Either no completed purchase at all, or every one either
            // expired or has no active Hulahoot companion row configured -
            // matches SubscriptionPackage's own "no row = not an error,
            // just unconfigured" convention, just applied to the gating
            // decision instead of a display default.
            return null;
        }

        $fnMergeLimit = function ($sField) use ($aActive) {
            $iSum = 0;
            foreach ($aActive as $aPurchase) {
                if ($aPurchase[$sField] === null) {
                    return null; // any unlimited purchase makes the merge unlimited
                }
                $iSum += (int)$aPurchase[$sField];
            }
            return $iSum;
        };

        $iMonthlyCredits = 0;
        $bSwessEnabled = false;
        $aTitles = [];
        foreach ($aActive as $aPurchase) {
            $iMonthlyCredits += (int)$aPurchase['monthly_credits'];
            $bSwessEnabled = $bSwessEnabled || (bool)$aPurchase['swess_enabled'];
            $sTitle = _p($aPurchase['package_title']);
            if (!in_array($sTitle, $aTitles, true)) {
                $aTitles[] = $sTitle;
            }
        }

        $aSoonest = $aActive[0]; // sorted by expiry_date ASC above

        return [
            'package_title' => implode(', ', $aTitles),
            'purchase_id' => (int)$aSoonest['purchase_id'],
            'package_id' => (int)$aSoonest['package_id'],
            'status' => $aSoonest['status'],
            'expiry_date' => $aSoonest['expiry_date'],
            'active_purchases' => array_map(function ($aPurchase) {
                return [
                    'purchase_id' => (int)$aPurchase['purchase_id'],
                    'package_id' => (int)$aPurchase['package_id'],
                    'package_title' => _p($aPurchase['package_title']),
                    'expiry_date' => $aPurchase['expiry_date'],
                ];
            }, $aActive),
            'purchase_limit' => $fnMergeLimit('purchase_limit'),
            'campaign_limit' => $fnMergeLimit('campaign_limit'),
            'posting_limit_per_day' => $fnMergeLimit('posting_limit_per_day'),
            'posting_limit_per_month' => $fnMergeLimit('posting_limit_per_month'),
            'monthly_credits' => $iMonthlyCredits,
            'swess_enabled' => $bSwessEnabled,
            'promotions_used' => 0,
            'campaigns_used' => 0,
        ];
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
