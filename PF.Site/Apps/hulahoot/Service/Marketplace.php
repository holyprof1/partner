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
                '(hspi.industry_id = ' . $iIndustryId . ' OR NOT EXISTS ('
                . 'SELECT 1 FROM ' . Phpfox::getT('hulahoot_subscription_package_industry')
                . ' x WHERE x.package_id = hsp.package_id'
                . ')) AND hsp.is_active = 1 AND sp.is_active = 1 AND sp.is_removed = 0'
            )
            ->order('hsp.ordering ASC, sp.ordering ASC')
            ->execute('getSlaveRows');

        $sDefaultCurrencyId = Phpfox::getService('core.currency')->getDefault();
        $oFeatureService = new SubscriptionPackageAdmin();

        foreach ($aRows as &$aRow) {
            $aRow['package_id'] = (int)$aRow['package_id'];
            $aRow['features'] = array_column($oFeatureService->getFeaturesForPackage($aRow['package_id']), 'feature_text');

            $aCosts = Phpfox::getLib('parse.format')->isSerialized($aRow['cost']) ? unserialize($aRow['cost']) : [];
            $aRow['default_cost'] = $aCosts[$sDefaultCurrencyId] ?? 0;
            $aRow['default_currency_id'] = $sDefaultCurrencyId;
        }
        unset($aRow);

        return $aRows;
    }
}
