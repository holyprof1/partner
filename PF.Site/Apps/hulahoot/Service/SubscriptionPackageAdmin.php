<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class SubscriptionPackageAdmin
 *
 * AdminCP-only read/write for the Phase 2 companion overlay
 * (hulahoot_subscription_package, hulahoot_subscription_package_industry,
 * hulahoot_subscription_package_feature) - never for
 * subscribe_package/subscribe_purchase themselves. Package creation,
 * price, billing period, and renewal all stay exclusively in
 * Core Subscriptions' own AdminCP (subscribe.admincp.*) - this service
 * only ever reads the native package list for reference and writes to
 * the Hulahoot companion tables. See docs/PHASE_2_SUBSCRIPTION.md.
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
                ->from(':hulahoot_subscription_package_industry')
                ->where(['package_id' => $iPackageId])
                ->execute('getSlaveField');
        }
        unset($aPackage);

        // Active packages first (the ones an admin actually needs to
        // manage day to day), inactive ones pushed below - a stable sort
        // so packages within each group keep whatever order the native
        // service returned them in.
        usort($aPackages, function ($aLeft, $aRight) {
            return (int)$aRight['is_active'] <=> (int)$aLeft['is_active'];
        });

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
     * configuration at all, which is the correct default, not an error
     * state.
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
            'display_name' => '',
            'subtitle' => '',
            'description' => '',
            'badge_text' => '',
            'image' => '',
            'accent_color' => '',
            'button_text' => '',
            'ordering' => 0,
            'purchase_limit' => null,
            'campaign_limit' => null,
            'posting_limit_per_day' => null,
            'posting_limit_per_month' => null,
            'monthly_credits' => 0,
            'is_active' => 0,
        ];
    }

    /**
     * Every active Industry, for the package-edit checkbox list and any
     * other admin picker. Includes inactive ones too when $bActiveOnly is
     * false, so an admin editing a package that's linked to an Industry
     * they've since deactivated can still see (and unlink) it.
     *
     * @param bool $bActiveOnly
     *
     * @return array
     */
    public function getAllIndustries($bActiveOnly = false)
    {
        $oQuery = db()->select('industry_id, name, slug, is_active')
            ->from(':hulahoot_industry');

        if ($bActiveOnly) {
            $oQuery->where(['is_active' => 1]);
        }

        return (array)$oQuery->order('sort_order ASC, name ASC')->execute('getSlaveRows');
    }

    /**
     * industry_id list currently linked to a package.
     *
     * @param int $iPackageId
     *
     * @return int[]
     */
    public function getIndustryIdsForPackage($iPackageId)
    {
        $aRows = (array)db()->select('industry_id')
            ->from(':hulahoot_subscription_package_industry')
            ->where(['package_id' => (int)$iPackageId])
            ->execute('getSlaveRows');

        return array_map('intval', array_column($aRows, 'industry_id'));
    }

    /**
     * Every native package currently assigned to one Industry, active or
     * not (an admin managing an Industry's lineup needs to see everything
     * assigned to it, same reasoning as listAll()), with its companion
     * rules merged in. Backs the "click an Industry, see its packages"
     * AdminCP screen.
     *
     * @param int $iIndustryId
     *
     * @return array
     */
    public function getPackagesForIndustryAdmin($iIndustryId)
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            return [];
        }

        $aPackageIds = (array)db()->select('package_id')
            ->from(':hulahoot_subscription_package_industry')
            ->where(['industry_id' => (int)$iIndustryId])
            ->execute('getSlaveRows');
        $aPackageIds = array_map('intval', array_column($aPackageIds, 'package_id'));

        if (!$aPackageIds) {
            return [];
        }

        $aPackages = [];
        foreach ($aPackageIds as $iPackageId) {
            $aNative = $this->getNativePackage($iPackageId);
            if ($aNative) {
                $aNative['hulahoot_rules'] = $this->getRules($iPackageId);
                $aPackages[] = $aNative;
            }
        }

        usort($aPackages, function ($aLeft, $aRight) {
            return (int)$aRight['is_active'] <=> (int)$aLeft['is_active'];
        });

        return $aPackages;
    }

    /**
     * Every native package NOT currently assigned to this Industry - the
     * candidate list for a quick "assign an existing package" picker, so
     * an admin doesn't have to open each package's full Edit Rules form
     * just to add one Industry link.
     *
     * @param int $iIndustryId
     *
     * @return array
     */
    public function getUnassignedPackages($iIndustryId)
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            return [];
        }

        $aAssignedIds = (array)db()->select('package_id')
            ->from(':hulahoot_subscription_package_industry')
            ->where(['industry_id' => (int)$iIndustryId])
            ->execute('getSlaveRows');
        $aAssignedIds = array_map('intval', array_column($aAssignedIds, 'package_id'));

        $aAllPackages = (array)Phpfox::getService('subscribe')->getPackages(false, true, true);

        return array_values(array_filter($aAllPackages, function ($aPackage) use ($aAssignedIds) {
            return !in_array((int)$aPackage['package_id'], $aAssignedIds, true);
        }));
    }

    /**
     * Links one existing package to one Industry without touching any of
     * its other configuration - the "Assign" side of the quick picker.
     * No-ops (rather than erroring) if the link already exists, since a
     * double-click or a stale page shouldn't produce a duplicate row (no
     * table here has a hard unique constraint - see the migration class
     * docblocks - so this check is what keeps it a true no-op).
     *
     * @param int $iPackageId
     * @param int $iIndustryId
     *
     * @return void
     */
    public function assignIndustryToPackage($iPackageId, $iIndustryId)
    {
        $iPackageId = (int)$iPackageId;
        $iIndustryId = (int)$iIndustryId;

        $bExists = (bool)db()->select('id')
            ->from(':hulahoot_subscription_package_industry')
            ->where(['package_id' => $iPackageId, 'industry_id' => $iIndustryId])
            ->execute('getSlaveField');

        if (!$bExists) {
            db()->insert(':hulahoot_subscription_package_industry', [
                'package_id' => $iPackageId,
                'industry_id' => $iIndustryId,
            ]);
        }
    }

    /**
     * The inverse of assignIndustryToPackage() - unlinks one package from
     * one Industry only. Never touches the package's rules, its other
     * Industry links, or the native package itself.
     *
     * @param int $iPackageId
     * @param int $iIndustryId
     *
     * @return void
     */
    public function removeIndustryFromPackage($iPackageId, $iIndustryId)
    {
        db()->delete(':hulahoot_subscription_package_industry', [
            'package_id' => (int)$iPackageId,
            'industry_id' => (int)$iIndustryId,
        ]);
    }

    /**
     * Every feature row for a package, in display order.
     *
     * @param int $iPackageId
     *
     * @return array
     */
    public function getFeaturesForPackage($iPackageId)
    {
        return (array)db()->select('*')
            ->from(':hulahoot_subscription_package_feature')
            ->where(['package_id' => (int)$iPackageId])
            ->order('ordering ASC, id ASC')
            ->execute('getSlaveRows');
    }

    /**
     * Upserts the companion rules row, replaces the package's Industry
     * links wholesale, and replaces its feature list wholesale
     * (delete-then-reinsert the set - simplest correct approach for a
     * small, admin-only, low-frequency multi-select/list; no table has a
     * hard FK constraint - see the migration class docblocks - so
     * uniqueness of (package_id, industry_id) is enforced here, before
     * insert, same convention as ProfileTypeAdmin's is_default handling).
     *
     * @param int $iPackageId must already exist in subscribe_package
     * @param array $aData subtitle, description, badge_text, accent_color,
     *        button_text, ordering, purchase_limit, campaign_limit,
     *        posting_limit_per_day, posting_limit_per_month,
     *        monthly_credits, is_active
     * @param int[] $aIndustryIds
     * @param string[] $aFeatureTexts in display order - blank entries are
     *        dropped, order in the array becomes the stored ordering
     * @param string|null $sImagePath already-uploaded path (see
     *        Service\ImageUpload::upload()), or null to leave the
     *        existing image untouched (i.e. no new file was uploaded this
     *        submit) - same convention as IndustryAdmin::update()'s
     *        banner/thumbnail params.
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the native package doesn't exist
     */
    public function saveRules($iPackageId, array $aData, array $aIndustryIds, array $aFeatureTexts = [], $sImagePath = null)
    {
        $iPackageId = (int)$iPackageId;

        if (!$this->getNativePackage($iPackageId)) {
            throw new \InvalidArgumentException('Subscription package ' . $iPackageId . ' does not exist.');
        }

        $aClean = $this->_validate($aData);
        $aIndustryIds = array_values(array_unique(array_map('intval', $aIndustryIds)));
        $aFeatureTexts = array_values(array_filter(array_map('trim', $aFeatureTexts), function ($sText) {
            return $sText !== '';
        }));

        db()->beginTransaction();

        try {
            $aExisting = db()->select('image')
                ->from(':hulahoot_subscription_package')
                ->where(['package_id' => $iPackageId])
                ->execute('getSlaveRow');

            $aClean['image'] = $sImagePath !== null ? $sImagePath : ($aExisting['image'] ?? null);

            if ($aExisting) {
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

            db()->delete(':hulahoot_subscription_package_industry', ['package_id' => $iPackageId]);

            foreach ($aIndustryIds as $iIndustryId) {
                db()->insert(':hulahoot_subscription_package_industry', [
                    'package_id' => $iPackageId,
                    'industry_id' => $iIndustryId,
                ]);
            }

            db()->delete(':hulahoot_subscription_package_feature', ['package_id' => $iPackageId]);

            foreach ($aFeatureTexts as $iOrder => $sFeatureText) {
                db()->insert(':hulahoot_subscription_package_feature', [
                    'package_id' => $iPackageId,
                    'feature_text' => $sFeatureText,
                    'ordering' => $iOrder,
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

        $sAccentColor = trim((string)($aData['accent_color'] ?? ''));
        if ($sAccentColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $sAccentColor)) {
            throw new \InvalidArgumentException('Accent color must be a hex value (e.g. #2C7BE5), or left blank.');
        }

        $sDisplayName = trim((string)($aData['display_name'] ?? ''));

        return [
            'display_name' => $sDisplayName !== '' ? $sDisplayName : null,
            'subtitle' => trim((string)($aData['subtitle'] ?? '')),
            'description' => trim((string)($aData['description'] ?? '')),
            'badge_text' => trim((string)($aData['badge_text'] ?? '')),
            'accent_color' => $sAccentColor,
            'button_text' => trim((string)($aData['button_text'] ?? '')),
            'ordering' => max(0, (int)($aData['ordering'] ?? 0)),
            'purchase_limit' => $fParseLimit($aData['purchase_limit'] ?? null),
            'campaign_limit' => $fParseLimit($aData['campaign_limit'] ?? null),
            'posting_limit_per_day' => $fParseLimit($aData['posting_limit_per_day'] ?? null),
            'posting_limit_per_month' => $fParseLimit($aData['posting_limit_per_month'] ?? null),
            'monthly_credits' => max(0, (int)($aData['monthly_credits'] ?? 0)),
            'is_active' => !empty($aData['is_active']) ? 1 : 0,
        ];
    }
}
