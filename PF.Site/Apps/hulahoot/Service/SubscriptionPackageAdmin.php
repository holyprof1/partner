<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class SubscriptionPackageAdmin
 *
 * AdminCP-only read/write for the Phase 2 companion overlay
 * (hulahoot_subscription_package, hulahoot_subscription_package_category)
 * - never for subscribe_package/subscribe_purchase themselves. Package
 * creation, price, billing period, and renewal all stay exclusively in
 * Core Subscriptions' own AdminCP (subscribe.admincp.*) - this service
 * only ever reads the native package list for reference and writes to
 * the two new companion tables. See docs/PHASE_2_SUBSCRIPTION.md.
 *
 * Every public method here is meant to be called only from an
 * admincp/hulahoot/* route, after the caller has already checked
 * admincp.has_admin_access - matches ProfileTypeAdmin/ProfileCategoryAdmin's
 * own documented assumption.
 *
 * @package Apps\Hulahoot\Service
 */
class SubscriptionPackageAdmin
{
    /**
     * Every native subscription package (active or not - an admin editing
     * rules needs to see everything, same reasoning as
     * ProfileTypeAdmin::getById() including inactive rows), with whichever
     * Hulahoot companion row exists merged in.
     *
     * @return array
     */
    public function listAll()
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            return [];
        }

        $aPackages = (array)Phpfox::getService('subscribe')->getPackages(false, true, true);

        $aRules = db()->select('*')
            ->from(':hulahoot_subscription_package')
            ->execute('getSlaveRows');
        $aRulesByPackageId = [];
        foreach ((array)$aRules as $aRule) {
            $aRulesByPackageId[(int)$aRule['package_id']] = $aRule;
        }

        foreach ($aPackages as &$aPackage) {
            $iPackageId = (int)$aPackage['package_id'];
            $aPackage['hulahoot_rules'] = $aRulesByPackageId[$iPackageId] ?? null;
            $aPackage['hulahoot_industry_count'] = (int)db()->select('COUNT(*)')
                ->from(':hulahoot_subscription_package_category')
                ->where(['package_id' => $iPackageId])
                ->execute('getSlaveField');
        }
        unset($aPackage);

        return $aPackages;
    }

    /**
     * One native package, by id, regardless of its own is_active - same
     * reasoning as listAll(). False if no such package exists natively.
     *
     * @param int $iPackageId
     *
     * @return array|false
     */
    public function getNativePackage($iPackageId)
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            return false;
        }

        return Phpfox::getService('subscribe')->getPackage((int)$iPackageId, true);
    }

    /**
     * The Hulahoot companion rules row for a package, or the documented
     * defaults (unlimited / zero / inactive-until-configured) if none
     * exists yet - a package with no row here has no Hulahoot-specific
     * limits at all, which is the correct default, not an error state.
     *
     * @param int $iPackageId
     *
     * @return array
     */
    public function getRules($iPackageId)
    {
        $aRule = db()->select('*')
            ->from(':hulahoot_subscription_package')
            ->where(['package_id' => (int)$iPackageId])
            ->execute('getSlaveRow');

        if ($aRule) {
            return $aRule;
        }

        return [
            'package_id' => (int)$iPackageId,
            'purchase_limit' => null,
            'posting_limit_per_day' => null,
            'posting_limit_per_month' => null,
            'monthly_credits' => 0,
            'is_active' => 0,
        ];
    }

    /**
     * category_id => name for every Hulahoot Profile Category
     * ("Industry"), across every Profile Type - not restricted to
     * Business/Organization, so a future vertical's own categories are
     * automatically available here with no code change.
     *
     * @return array
     */
    public function getAllIndustries()
    {
        return (array)db()->select('category_id, name, profile_type_id')
            ->from(':hulahoot_profile_category')
            ->where(['parent_id' => 0])
            ->order('name ASC')
            ->execute('getSlaveRows');
    }

    /**
     * category_id list currently linked to a package.
     *
     * @param int $iPackageId
     *
     * @return int[]
     */
    public function getIndustryIdsForPackage($iPackageId)
    {
        $aRows = (array)db()->select('category_id')
            ->from(':hulahoot_subscription_package_category')
            ->where(['package_id' => (int)$iPackageId])
            ->execute('getSlaveRows');

        return array_map('intval', array_column($aRows, 'category_id'));
    }

    /**
     * Upserts the companion rules row and replaces the package's industry
     * links wholesale (delete-then-reinsert the set - simplest correct
     * approach for a small, admin-only, low-frequency multi-select; no
     * table has a hard FK constraint - see the migration class docblocks -
     * so uniqueness of (package_id, category_id) is enforced here, before
     * insert, same convention as ProfileTypeAdmin's is_default handling).
     *
     * @param int $iPackageId must already exist in subscribe_package
     * @param array $aData purchase_limit, posting_limit_per_day,
     *        posting_limit_per_month, monthly_credits, is_active
     * @param int[] $aIndustryCategoryIds
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the native package doesn't exist
     */
    public function saveRules($iPackageId, array $aData, array $aIndustryCategoryIds)
    {
        $iPackageId = (int)$iPackageId;

        if (!$this->getNativePackage($iPackageId)) {
            throw new \InvalidArgumentException('Subscription package ' . $iPackageId . ' does not exist.');
        }

        $aClean = $this->_validate($aData);
        $aIndustryCategoryIds = array_values(array_unique(array_map('intval', $aIndustryCategoryIds)));

        db()->beginTransaction();

        try {
            $bExists = (bool)db()->select('package_id')
                ->from(':hulahoot_subscription_package')
                ->where(['package_id' => $iPackageId])
                ->execute('getSlaveField');

            if ($bExists) {
                db()->update(':hulahoot_subscription_package', array_merge($aClean, [
                    'updated' => time(),
                ]), ['package_id' => $iPackageId]);
            } else {
                db()->insert(':hulahoot_subscription_package', array_merge($aClean, [
                    'package_id' => $iPackageId,
                    'created' => time(),
                    'updated' => time(),
                ]));
            }

            db()->delete(':hulahoot_subscription_package_category', ['package_id' => $iPackageId]);

            foreach ($aIndustryCategoryIds as $iCategoryId) {
                db()->insert(':hulahoot_subscription_package_category', [
                    'package_id' => $iPackageId,
                    'category_id' => $iCategoryId,
                ]);
            }

            db()->commit();
        } catch (\Exception $e) {
            db()->rollback();
            throw $e;
        }

        return true;
    }

    /**
     * Shared saveRules() field validation. Returns the clean, DB-ready
     * field array (never the raw input).
     *
     * @param array $aData
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    private function _validate(array $aData)
    {
        $fParseLimit = function ($mValue) {
            $sValue = trim((string)($mValue ?? ''));
            if ($sValue === '') {
                return null;
            }
            if (!ctype_digit($sValue)) {
                throw new \InvalidArgumentException('Limits must be a whole number, or left blank for unlimited.');
            }

            return (int)$sValue;
        };

        return [
            'purchase_limit' => $fParseLimit($aData['purchase_limit'] ?? null),
            'posting_limit_per_day' => $fParseLimit($aData['posting_limit_per_day'] ?? null),
            'posting_limit_per_month' => $fParseLimit($aData['posting_limit_per_month'] ?? null),
            'monthly_credits' => max(0, (int)($aData['monthly_credits'] ?? 0)),
            'is_active' => !empty($aData['is_active']) ? 1 : 0,
        ];
    }
}
