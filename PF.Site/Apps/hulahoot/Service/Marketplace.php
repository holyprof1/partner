<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class Marketplace
 *
 * Public, read-only browsing surface for Industries and their assigned
 * Packages - the "Find Your Industry -> Industry -> Packages" flow.
 * Deliberately separate from Service\IndustryAdmin /
 * Service\SubscriptionPackageAdmin (both AdminCP-only, both permission-gated
 * by their callers checking admincp access first) - this class is called
 * from public, logged-in-member routes instead, and only ever reads
 * *active* rows: an Industry or package an admin has deactivated should
 * disappear from the public site immediately without being deletable or
 * otherwise special-cased.
 *
 * @package Apps\Hulahoot\Service
 */
class Marketplace
{
    /**
     * Documented DEFAULT for a completed purchase's partnership term -
     * used only if the AdminCP setting (hulahoot.subscription_term_days,
     * see Install.php's setSettings()) is somehow unset. Everywhere else
     * in this app that needs the real, currently-configured value calls
     * getSubscriptionTermDays() instead of reading this constant
     * directly - a PHP class constant can't be changed at runtime, so it
     * can only ever serve as the fallback, never the live value.
     */
    const SUBSCRIPTION_TERM_DAYS = 365;

    /**
     * Documented DEFAULT for the grace period - see
     * SUBSCRIPTION_TERM_DAYS's own docblock for why this is a fallback
     * only; getGracePeriodDays() is the real accessor.
     */
    const GRACE_PERIOD_DAYS = 30;

    /**
     * @return int the currently admin-configured subscription term, in
     *         days - see SUBSCRIPTION_TERM_DAYS's own docblock
     */
    public static function getSubscriptionTermDays()
    {
        $iValue = (int)Phpfox::getParam('hulahoot.subscription_term_days');

        return $iValue > 0 ? $iValue : self::SUBSCRIPTION_TERM_DAYS;
    }

    /**
     * @return int the currently admin-configured grace period, in days -
     *         see GRACE_PERIOD_DAYS's own docblock
     */
    public static function getGracePeriodDays()
    {
        $iValue = (int)Phpfox::getParam('hulahoot.grace_period_days');

        return $iValue > 0 ? $iValue : self::GRACE_PERIOD_DAYS;
    }

    /**
     * Every active Industry, for the "Find Your Industry" browse/search
     * page - in the same order priorities.
     *
     * @return array
     */
    public function getActiveIndustries()
    {
        return (array)db()->select('*')
            ->from(':hulahoot_industry')
            ->where(['is_active' => 1])
            ->order('sort_order ASC, name ASC')
            ->execute('getSlaveRows');
    }

    /**
     * One active Industry by its public slug, or false if it doesn't
     * exist or has been deactivated - the detail page treats both cases
     * identically (not found), so a deactivated Industry's page simply
     * stops resolving rather than showing stale content.
     *
     * @param string $sSlug
     *
     * @return array|false
     */
    public function getActiveIndustryBySlug($sSlug)
    {
        return db()->select('*')
            ->from(':hulahoot_industry')
            ->where(['slug' => (string)$sSlug, 'is_active' => 1])
            ->execute('getSlaveRow');
    }

    /**
     * Every package assigned to $iIndustryId that's purchasable right
     * now: active natively (subscribe_package.is_active) AND active in
     * the Hulahoot companion row (hulahoot_subscription_package.is_active -
     * the separate kill switch documented on that table). A package with
     * no companion row at all never appears here, since it can't be
     * linked to any Industry without one (the link only gets written
     * alongside the companion row - see
     * SubscriptionPackageAdmin::saveRules()).
     *
     * Each returned package includes its ordered feature list under
     * 'features'.
     *
     * @param int $iIndustryId
     *
     * @return array in hulahoot_subscription_package.ordering order
     */
    public function getPackagesForIndustry($iIndustryId)
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            return [];
        }

        $iIndustryId = (int)$iIndustryId;

        // LEFT JOIN scoped to this industry, rather than an INNER JOIN, so
        // a package with zero rows in hulahoot_subscription_package_industry
        // still matches - that's what makes "leave all unchecked" in the
        // package edit form actually mean "available to every industry"
        // (hulahoot_industries_help) instead of "available to none", which
        // is what an INNER JOIN here silently did.
        $aRows = (array)db()->select('hsp.*, sp.title, sp.cost, sp.recurring_cost, sp.recurring_period')
            ->from(':hulahoot_subscription_package', 'hsp')
            ->leftJoin(':hulahoot_subscription_package_industry', 'hspi', 'hspi.package_id = hsp.package_id AND hspi.industry_id = ' . $iIndustryId)
            ->join(':subscribe_package', 'sp', 'sp.package_id = hsp.package_id')
            ->where(
                '(hspi.industry_id = ' . $iIndustryId . ' OR (hsp.is_open = 0 AND NOT EXISTS ('
                . 'SELECT 1 FROM ' . Phpfox::getT('hulahoot_subscription_package_industry')
                . ' x WHERE x.package_id = hsp.package_id'
                . '))) AND hsp.is_active = 1 AND hsp.is_locked_pending_admin = 0 AND sp.is_active = 1 AND sp.is_removed = 0'
            )
            ->order('hsp.ordering ASC, sp.ordering ASC')
            ->execute('getSlaveRows');

        $sDefaultCurrencyId = Phpfox::getService('core.currency')->getDefault();
        $oFeatureService = new SubscriptionPackageAdmin();

        foreach ($aRows as &$aRow) {
            $aRow['package_id'] = (int)$aRow['package_id'];
            $aRow['features'] = array_column($oFeatureService->getFeaturesForPackage($aRow['package_id']), 'feature_text');

            // display_name is plain text (set once by an admin, not a
            // phrase key) - resolved here, not in the template, so the
            // template never has to know whether a given package has an
            // override or needs the usual _p(package.title) phrase lookup.
            $aRow['display_name'] = $aRow['display_name'] !== null && $aRow['display_name'] !== ''
                ? $aRow['display_name']
                : _p($aRow['title']);

            $aCosts = Phpfox::getLib('parse.format')->isSerialized($aRow['cost']) ? unserialize($aRow['cost']) : [];
            $aRow['default_cost'] = $aCosts[$sDefaultCurrencyId] ?? 0;
            $aRow['default_currency_id'] = $sDefaultCurrencyId;

            $iLimit = $aRow['purchase_limit'] !== null ? (int)$aRow['purchase_limit'] : null;
            $aRow['slots_remaining'] = $iLimit !== null
                ? max(0, $iLimit - $this->getOccupiedSlotCount($aRow['package_id']))
                : null;
            $aRow['is_sold_out'] = $iLimit !== null && $aRow['slots_remaining'] <= 0;
        }
        unset($aRow);

        return $aRows;
    }

    /**
     * Every "Open Partnership" package that's purchasable right now - the
     * $4M formula PDF's separate, non-industry inventory track
     * (is_open = 1, see that column's own docblock for why this is
     * distinct from a package with no industry links). Same shape and
     * same active/not-removed/not-locked rules as
     * getPackagesForIndustry(), just scoped to is_open = 1 instead of a
     * specific Industry.
     *
     * @return array in hulahoot_subscription_package.ordering order
     */
    public function getOpenPackages()
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            return [];
        }

        $aRows = (array)db()->select('hsp.*, sp.title, sp.cost, sp.recurring_cost, sp.recurring_period')
            ->from(':hulahoot_subscription_package', 'hsp')
            ->join(':subscribe_package', 'sp', 'sp.package_id = hsp.package_id')
            ->where('hsp.is_open = 1 AND hsp.is_active = 1 AND hsp.is_locked_pending_admin = 0 AND sp.is_active = 1 AND sp.is_removed = 0')
            ->order('hsp.ordering ASC, sp.ordering ASC')
            ->execute('getSlaveRows');

        $sDefaultCurrencyId = Phpfox::getService('core.currency')->getDefault();
        $oFeatureService = new SubscriptionPackageAdmin();

        foreach ($aRows as &$aRow) {
            $aRow['package_id'] = (int)$aRow['package_id'];
            $aRow['features'] = array_column($oFeatureService->getFeaturesForPackage($aRow['package_id']), 'feature_text');
            $aRow['display_name'] = $aRow['display_name'] !== null && $aRow['display_name'] !== ''
                ? $aRow['display_name']
                : _p($aRow['title']);

            $aCosts = Phpfox::getLib('parse.format')->isSerialized($aRow['cost']) ? unserialize($aRow['cost']) : [];
            $aRow['default_cost'] = $aCosts[$sDefaultCurrencyId] ?? 0;
            $aRow['default_currency_id'] = $sDefaultCurrencyId;

            $iLimit = $aRow['purchase_limit'] !== null ? (int)$aRow['purchase_limit'] : null;
            $aRow['slots_remaining'] = $iLimit !== null
                ? max(0, $iLimit - $this->getOccupiedSlotCount($aRow['package_id']))
                : null;
            $aRow['is_sold_out'] = $iLimit !== null && $aRow['slots_remaining'] <= 0;
        }
        unset($aRow);

        return $aRows;
    }

    /**
     * How many of a package's slots are currently spoken for: completed
     * purchases still inside their 1-year term, OR - ONLY for a package
     * with is_renewable = 1 (AdminCP -> Subscription Packages -> "Eligible
     * for Renewal") - inside the extra GRACE_PERIOD_DAYS renewal window
     * after that too. Confirmed requirement: "Domination is the only
     * package eligible for the 30 day renewal/grace flow" - a
     * non-renewable package's slot frees up the instant its raw
     * expiry_date passes, no grace hold at all; a renewable one keeps
     * holding the slot through its grace window same as before. A
     * purchase whose applicable window has fully lapsed no longer counts
     * - its slot is back on the market, even though the purchase row
     * itself still exists (native purchase history is never deleted).
     *
     * A purchase with expiry_date = 0 (native's "never expires" marker)
     * always counts - matches how native Core Subscriptions itself
     * treats a zero expiry_date.
     *
     * @param int $iPackageId
     *
     * @return int
     */
    public function getOccupiedSlotCount($iPackageId)
    {
        $iPackageId = (int)$iPackageId;

        $bIsRenewable = (bool)db()->select('is_renewable')
            ->from(':hulahoot_subscription_package')
            ->where(['package_id' => $iPackageId])
            ->execute('getSlaveField');

        $iCutoff = $bIsRenewable ? (time() - (self::getGracePeriodDays() * 86400)) : time();

        return (int)db()->select('COUNT(*)')
            ->from(':subscribe_purchase')
            ->where(
                'package_id = ' . $iPackageId
                . ' AND status = "completed"'
                . ' AND (expiry_date = 0 OR expiry_date > ' . $iCutoff . ')'
            )
            ->execute('getSlaveField');
    }

    /**
     * Daily sweep (called from cron alongside Service\ExpiryReminders -
     * see send-expiry-reminders.php): finds every renewable package
     * (is_renewable = 1) where a holder's grace window has JUST fully
     * lapsed - crossed from "still counted as occupying a slot" to "no
     * longer does", per getOccupiedSlotCount()'s own cutoff - with no
     * OTHER still-valid purchase of theirs against that same package
     * covering them (i.e. they never renewed by buying it again before
     * time ran out), and locks that package. Confirmed requirement: "If
     * the holder doesn't renew within the 30 day grace period. The
     * package is hidden and only admin can make it active or put it back
     * on the market again."
     *
     * Deliberately package-level, not per-slot: a multi-slot renewable
     * package (e.g. "Automotive - Domination" with purchase_limit > 1)
     * gets hidden from new purchases entirely the moment any ONE holder's
     * window lapses unrenewed, exactly as the confirmed requirement
     * states ("the package is hidden") - existing holders' own access is
     * untouched either way (Entitlement/Swess already gate on each
     * purchase's own expiry_date, never on this flag).
     *
     * Idempotent: only locks a package still sitting at
     * is_locked_pending_admin = 0, and only admin unchecking that box
     * (SubscriptionPackageAddController) ever clears it - this sweep
     * never clears it itself, and never re-locks an already-locked one.
     *
     * @return int how many packages were newly locked this run
     */
    public function lockExpiredRenewablePackages()
    {
        $iNow = time();
        $iGraceCutoff = $iNow - (self::getGracePeriodDays() * 86400);
        // Anything that lapsed more than a day before the cutoff was
        // already caught by yesterday's run (or is stale data predating
        // this feature) - only look at what crossed the line since the
        // last time this ran, so a long-lapsed purchase an admin already
        // reviewed and released doesn't get immediately re-locked next
        // time this sweep runs.
        $iPriorCutoff = $iGraceCutoff - 86400;

        $aLapsed = db()->select('sp.purchase_id, sp.user_id, sp.package_id')
            ->from(':subscribe_purchase', 'sp')
            ->join(':hulahoot_subscription_package', 'hsp', 'hsp.package_id = sp.package_id')
            ->where(
                'sp.status = "completed" AND hsp.is_renewable = 1 AND hsp.is_locked_pending_admin = 0'
                . ' AND sp.expiry_date > 0 AND sp.expiry_date <= ' . $iPriorCutoff
                . ' AND sp.expiry_date > ' . ($iPriorCutoff - 86400)
            )
            ->execute('getSlaveRows');

        $iLocked = 0;
        foreach ($aLapsed as $aPurchase) {
            $iPackageId = (int)$aPurchase['package_id'];
            $iUserId = (int)$aPurchase['user_id'];

            // Did this same user renew (buy this same package again) in
            // time? Any completed purchase of theirs against this package
            // that's still within its own term/grace covers them.
            $bStillCovered = (bool)db()->select('purchase_id')
                ->from(':subscribe_purchase')
                ->where(
                    'user_id = ' . $iUserId . ' AND package_id = ' . $iPackageId
                    . ' AND status = "completed"'
                    . ' AND (expiry_date = 0 OR expiry_date > ' . $iGraceCutoff . ')'
                )
                ->execute('getSlaveField');

            if ($bStillCovered) {
                continue;
            }

            $n = db()->update(':hulahoot_subscription_package', [
                'is_locked_pending_admin' => 1,
            ], ['package_id' => $iPackageId, 'is_locked_pending_admin' => 0]);

            if ($n > 0) {
                $iLocked++;
                Phpfox::getLog('hulahoot.log')->info(
                    'Marketplace::lockExpiredRenewablePackages(): package ' . $iPackageId
                    . ' locked pending admin review - purchase ' . (int)$aPurchase['purchase_id']
                    . ' (user ' . $iUserId . ') lapsed its grace window unrenewed.'
                );
            }
        }

        return $iLocked;
    }

    /**
     * Corrects a real, gateway-paid purchase's expiry_date from native's
     * 0 ("never expires") to Hulahoot's actual configured term, for every
     * completed Hulahoot-managed purchase this user holds that still
     * needs it.
     *
     * Why this exists: PurchaseFlow's own direct-completion paths
     * (completeAsHulahoot() for free/admin-preview, createExpansionSlot()
     * for Buy Out's extra slots) already stamp a correct real expiry_date
     * at creation time. A purchase completed for real through the
     * payment gateway does not - native Callback.php (Apps\Core_
     * Subscriptions\Service\Callback::paymentApiCallback(), confirmed by
     * reading it directly) sets expiry_date = 0 for any completed,
     * non-recurring purchase (every Hulahoot package is recurring_period
     * = 0), which is native's own "never expires" marker - not what "All
     * qualifying plans are currently 365 days" actually means for
     * Hulahoot. This is what closes that gap.
     *
     * Deliberately lazy, not synchronous: Callback.php calls native
     * Purchase\Process::update() FIRST, then does its OWN expiry_date
     * writes using a STALE, pre-update() copy of the purchase row it
     * fetched earlier - confirmed by reading paymentApiCallback()
     * directly. Setting the correct expiry_date from inside
     * hooks/subscribe.service_purchase_process_update_pre_log.php (which
     * runs INSIDE that same update() call) would just get silently
     * overwritten back to 0 moments later by that stale-copy logic, since
     * Callback.php has no plugin hook of its own to intercept instead
     * (confirmed: the only hook in Callback.php lives inside its unrelated
     * __call() fallback). So this runs afterward instead, on the same
     * lazy/idempotent "pull, reconcile to current truth" schedule as
     * Service\Swess::syncPackageEntitlement() and Service\PurchaseFlow::
     * expandAllPendingBuyouts() - called from Swess::syncPackageEntitlement()
     * itself (so every existing SWESS entry point already covers this
     * for free) and from the /industry and /find-your-industry routes.
     *
     * Naturally idempotent: only ever touches a row still sitting at
     * expiry_date = 0 - once corrected, it's a real future (or past)
     * timestamp and this method has nothing left to do for it on any
     * later call.
     *
     * @param int $iUserId
     *
     * @return void
     */
    public function reconcilePurchaseTermsForUser($iUserId)
    {
        $iUserId = (int)$iUserId;

        $aPurchases = db()->select('sp.purchase_id, sp.time_stamp')
            ->from(':subscribe_purchase', 'sp')
            ->join(':hulahoot_subscription_package', 'hsp', 'hsp.package_id = sp.package_id')
            ->where(['sp.user_id' => $iUserId, 'sp.status' => 'completed', 'sp.expiry_date' => 0])
            ->execute('getSlaveRows');

        if (!$aPurchases) {
            return;
        }

        $iTermSeconds = self::getSubscriptionTermDays() * 86400;

        foreach ($aPurchases as $aPurchase) {
            // Anchored to the purchase's own completion time_stamp (when
            // it actually completed), not "now" - a purchase reconciled
            // a week after it completed must still expire 365 days from
            // when it was PAID, not 365 days from whenever this lazy fix
            // happened to run.
            $iExpiryDate = (int)$aPurchase['time_stamp'] + $iTermSeconds;

            db()->update(':subscribe_purchase', [
                'expiry_date' => $iExpiryDate,
            ], ['purchase_id' => (int)$aPurchase['purchase_id']]);
        }
    }
}
