<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class IndustryAdmin
 *
 * AdminCP-only CRUD for hulahoot_industry - the post-login marketplace
 * users browse before purchasing a subscription package. Deliberately
 * separate from ProfileTypeAdmin/ProfileCategoryAdmin, which own
 * registration/profile classification and never touch this table. See
 * Installation/Database/Industry.php and docs/PHASE_2_SUBSCRIPTION.md.
 *
 * Every public method here is meant to be called only from an
 * admincp/hulahoot/* route, after the caller has already checked
 * admincp.has_admin_access - matches every other *Admin service in this
 * app.
 *
 * @package Apps\Hulahoot\Service
 */
class IndustryAdmin
{
    /**
     * Every Industry, active or not, for the AdminCP browse screen - plus
     * a computed count of how many subscription packages currently link
     * to each one, matching the "counts computed on read, not stored"
     * convention already used elsewhere in this module.
     *
     * @return array
     */
    public function listAll()
    {
        $aIndustries = (array)db()->select('*')
            ->from(':hulahoot_industry')
            ->order('sort_order ASC, industry_id ASC')
            ->execute('getSlaveRows');

        foreach ($aIndustries as &$aIndustry) {
            $aIndustry['package_count'] = $this->countPackagesForIndustry((int)$aIndustry['industry_id']);
        }
        unset($aIndustry);

        return $aIndustries;
    }

    /**
     * How many packages would actually show for this Industry on the
     * storefront - matches Service\Marketplace::getPackagesForIndustry()'s
     * result set exactly (explicitly linked to this industry, OR linked to
     * no industry at all - "available to every industry" per
     * hulahoot_industries_help - and active/not-removed on both companion
     * and native rows), rather than a raw count of junction-table rows,
     * which underweighted "universal" packages and ignored active/removed
     * status entirely.
     *
     * @param int $iIndustryId
     *
     * @return int
     */
    public function countPackagesForIndustry($iIndustryId)
    {
        $iIndustryId = (int)$iIndustryId;

        return (int)db()->select('COUNT(*)')
            ->from(':hulahoot_subscription_package', 'hsp')
            ->leftJoin(':hulahoot_subscription_package_industry', 'hspi', 'hspi.package_id = hsp.package_id AND hspi.industry_id = ' . $iIndustryId)
            ->join(':subscribe_package', 'sp', 'sp.package_id = hsp.package_id')
            ->where(
                '(hspi.industry_id = ' . $iIndustryId . ' OR NOT EXISTS ('
                . 'SELECT 1 FROM ' . Phpfox::getT('hulahoot_subscription_package_industry')
                . ' x WHERE x.package_id = hsp.package_id'
                . ')) AND hsp.is_active = 1 AND sp.is_active = 1 AND sp.is_removed = 0'
            )
            ->execute('getSlaveField');
    }

    /**
     * Fetch one row regardless of is_active - an admin edit screen must
     * still be able to open and re-activate an inactive Industry.
     *
     * @param int $iIndustryId
     *
     * @return array|false
     */
    public function getById($iIndustryId)
    {
        return db()->select('*')
            ->from(':hulahoot_industry')
            ->where(['industry_id' => (int)$iIndustryId])
            ->execute('getSlaveRow');
    }

    /**
     * @param string $sSlug
     *
     * @return array|false
     */
    public function getBySlug($sSlug)
    {
        return db()->select('*')
            ->from(':hulahoot_industry')
            ->where(['slug' => (string)$sSlug])
            ->execute('getSlaveRow');
    }

    /**
     * @param array $aData name, slug, description, is_active, sort_order
     * @param string|null $sBannerPath already-uploaded path (see
     *        Service\ImageUpload::upload()), or null to leave unset
     * @param string|null $sThumbnailPath same
     *
     * @return int the new industry_id
     *
     * @throws \InvalidArgumentException on any validation failure (see _validate())
     */
    public function create(array $aData, $sBannerPath = null, $sThumbnailPath = null)
    {
        $aClean = $this->_validate($aData, null);
        $aClean['banner'] = $sBannerPath;
        $aClean['thumbnail'] = $sThumbnailPath;

        // New industries always append after the last one, so a fresh
        // row doesn't jump to the front of the public browse page.
        if (!isset($aData['sort_order']) || $aData['sort_order'] === '') {
            $aClean['sort_order'] = 1 + (int)db()->select('MAX(sort_order)')
                ->from(':hulahoot_industry')
                ->execute('getSlaveField');
        }

        return (int)db()->insert(':hulahoot_industry', array_merge($aClean, [
            'created_at' => time(),
            'updated_at' => time(),
        ]));
    }

    /**
     * @param int $iIndustryId
     * @param array $aData same fields as create()
     * @param string|null $sBannerPath pass null to leave the existing
     *        banner untouched (i.e. no new file was uploaded this submit)
     * @param string|null $sThumbnailPath same
     *
     * @return bool
     *
     * @throws \InvalidArgumentException on any validation failure
     */
    public function update($iIndustryId, array $aData, $sBannerPath = null, $sThumbnailPath = null)
    {
        $iIndustryId = (int)$iIndustryId;
        $aExisting = $this->getById($iIndustryId);

        if (!$aExisting) {
            throw new \InvalidArgumentException('Industry ' . $iIndustryId . ' does not exist.');
        }

        $aClean = $this->_validate($aData, $iIndustryId);
        $aClean['banner'] = $sBannerPath !== null ? $sBannerPath : $aExisting['banner'];
        $aClean['thumbnail'] = $sThumbnailPath !== null ? $sThumbnailPath : $aExisting['thumbnail'];
        $aClean['updated_at'] = time();

        db()->update(':hulahoot_industry', $aClean, ['industry_id' => $iIndustryId]);

        return true;
    }

    /**
     * @param int $iIndustryId
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the row doesn't exist or is still
     *         linked to any subscription package
     */
    public function delete($iIndustryId)
    {
        $iIndustryId = (int)$iIndustryId;
        $aExisting = $this->getById($iIndustryId);

        if (!$aExisting) {
            throw new \InvalidArgumentException('Industry ' . $iIndustryId . ' does not exist.');
        }

        $iPackageCount = (int)db()->select('COUNT(*)')
            ->from(':hulahoot_subscription_package_industry')
            ->where(['industry_id' => $iIndustryId])
            ->execute('getSlaveField');

        if ($iPackageCount > 0) {
            throw new \InvalidArgumentException(
                'Cannot delete - still linked to ' . $iPackageCount . ' subscription package(s). Unlink those first.'
            );
        }

        db()->delete(':hulahoot_industry', ['industry_id' => $iIndustryId]);

        return true;
    }

    /**
     * Shared create()/update() field validation. Returns the clean,
     * DB-ready field array (never the raw input, and never including
     * banner/thumbnail - callers merge those in separately since they
     * come from a different source: an uploaded file, not a form field).
     *
     * Unlike ProfileTypeAdmin's name_url (left blank if not typed - see
     * that class's docblock), slug is auto-generated from the name when
     * left blank, since Industry slugs are actually routed
     * (/industry/{slug}) rather than just stored.
     *
     * @param array $aData
     * @param int|null $iExcludeId when editing, this row's own id is
     *        excluded from the slug uniqueness check
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    private function _validate(array $aData, $iExcludeId)
    {
        $sName = trim((string)($aData['name'] ?? ''));
        if ($sName === '') {
            throw new \InvalidArgumentException('Name is required.');
        }
        if (function_exists('mb_strlen') ? mb_strlen($sName) > 100 : strlen($sName) > 100) {
            throw new \InvalidArgumentException('Name must be 100 characters or fewer.');
        }

        $sSlug = trim((string)($aData['slug'] ?? ''));
        $sSlug = $sSlug !== '' ? $this->_slugify($sSlug) : $this->_slugify($sName);

        if ($sSlug === '') {
            throw new \InvalidArgumentException('Could not derive a slug from that name - please set one explicitly.');
        }
        if (strlen($sSlug) > 100) {
            throw new \InvalidArgumentException('Slug must be 100 characters or fewer.');
        }

        $iExistingId = db()->select('industry_id')
            ->from(':hulahoot_industry')
            ->where(['slug' => $sSlug])
            ->execute('getSlaveField');

        if ($iExistingId && (int)$iExistingId !== (int)$iExcludeId) {
            throw new \InvalidArgumentException('That slug is already in use by another industry.');
        }

        $sDescription = trim((string)($aData['description'] ?? ''));

        return [
            'name' => $sName,
            'slug' => $sSlug,
            'description' => $sDescription !== '' ? $sDescription : null,
            'icon' => trim((string)($aData['icon'] ?? '')) ?: null,
            'sort_order' => (int)($aData['sort_order'] ?? 0),
            'is_active' => !empty($aData['is_active']) ? 1 : 0,
        ];
    }

    /**
     * Lowercase letters, numbers, and hyphens only - collapses any run of
     * other characters (spaces, punctuation) into a single hyphen, and
     * trims leading/trailing hyphens.
     *
     * @param string $sValue
     *
     * @return string
     */
    private function _slugify($sValue)
    {
        $sValue = strtolower(trim($sValue));
        $sValue = preg_replace('/[^a-z0-9]+/', '-', $sValue);

        return trim($sValue, '-');
    }
}
