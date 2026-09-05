<?php

namespace Apps\Hulahoot\Service;

/**
 * Class ProfileCategoryAdmin
 *
 * AdminCP-only CRUD for hulahoot_profile_category. Kept separate from
 * Service\Profile for the same reason as ProfileTypeAdmin - this is a
 * write surface only an admincp/hulahoot/* route should call, and keeping
 * it out of Service\Profile means nothing here can regress that class's
 * existing, already-exercised read paths.
 *
 * One row shape, two roles, per the existing schema (Installation/Database/
 * ProfileCategory.php): parent_id = 0 is a Category, parent_id pointing at
 * another row's category_id makes it a Subcategory of that row. Only one
 * level of nesting is enforced here (a Subcategory's own parent must
 * itself be a top-level Category) - the schema supports deeper nesting,
 * this class just doesn't expose it, matching the two-level model every
 * other part of this module (Service\Profile::create()/update()) already
 * assumes.
 *
 * Every public method here is meant to be called only from an
 * admincp/hulahoot/* route, after the caller has already checked
 * admincp.has_admin_access and the relevant hulahoot.can_* permission.
 *
 * @package Apps\Hulahoot\Service
 */
class ProfileCategoryAdmin
{
    /**
     * Every category/subcategory row for one Profile Type, active or not,
     * for the AdminCP browse screen - includes a computed profile-usage
     * count per row, and (for Category rows) a computed child-subcategory
     * count, both needed by the delete guard and shown in the browse list.
     *
     * @param int $iProfileTypeId
     *
     * @return array
     */
    public function listAllForType($iProfileTypeId)
    {
        $iProfileTypeId = (int)$iProfileTypeId;

        $aRows = (array)db()->select('*')
            ->from(':hulahoot_profile_category')
            ->where(['profile_type_id' => $iProfileTypeId])
            ->order('parent_id ASC, ordering ASC, category_id ASC')
            ->execute('getSlaveRows');

        foreach ($aRows as &$aRow) {
            $iCategoryId = (int)$aRow['category_id'];

            $aRow['profile_count'] = (int)db()->select('COUNT(*)')
                ->from(':hulahoot_profile')
                ->where('category_id = ' . $iCategoryId . ' OR subcategory_id = ' . $iCategoryId)
                ->execute('getSlaveField');

            $aRow['child_count'] = (int)$aRow['parent_id'] === 0
                ? (int)db()->select('COUNT(*)')
                    ->from(':hulahoot_profile_category')
                    ->where(['parent_id' => $iCategoryId])
                    ->execute('getSlaveField')
                : 0;
        }
        unset($aRow);

        return $aRows;
    }

    /**
     * @param int $iCategoryId
     *
     * @return array|false
     */
    public function getById($iCategoryId)
    {
        return db()->select('*')
            ->from(':hulahoot_profile_category')
            ->where(['category_id' => (int)$iCategoryId])
            ->execute('getSlaveRow');
    }

    /**
     * @param array $aData profile_type_id, parent_id, name, name_url, is_active, ordering
     *
     * @return int the new category_id
     *
     * @throws \InvalidArgumentException on any validation failure (see _validate())
     */
    public function create(array $aData)
    {
        $aClean = $this->_validate($aData, null);

        // New categories always append after their last sibling (same
        // profile_type_id + parent_id), regardless of whatever the
        // (now-secondary, drag-and-drop is primary) Sort Order field on
        // the form was left at - otherwise every new category defaults
        // to ordering=0 and jumps in front of its siblings.
        $aClean['ordering'] = 1 + (int)db()->select('MAX(ordering)')
            ->from(':hulahoot_profile_category')
            ->where([
                'profile_type_id' => $aClean['profile_type_id'],
                'parent_id' => $aClean['parent_id'],
            ])
            ->execute('getSlaveField');

        return (int)db()->insert(':hulahoot_profile_category', array_merge($aClean, [
            'created' => time(),
        ]));
    }

    /**
     * profile_type_id is immutable once set - re-parenting a row to a
     * different type would silently orphan any hulahoot_profile row
     * pointing at it by category_id/subcategory_id (matches
     * docs/AdminCPDesign.md §3.2). $aData['profile_type_id'] is ignored
     * here, not validated against - the existing row's own value is
     * always what's kept.
     *
     * @param int $iCategoryId
     * @param array $aData parent_id, name, name_url, is_active, ordering
     *
     * @return bool
     *
     * @throws \InvalidArgumentException on any validation failure
     */
    public function update($iCategoryId, array $aData)
    {
        $iCategoryId = (int)$iCategoryId;
        $aExisting = $this->getById($iCategoryId);

        if (!$aExisting) {
            throw new \InvalidArgumentException('Category ' . $iCategoryId . ' does not exist.');
        }

        $aData['profile_type_id'] = $aExisting['profile_type_id'];

        $aClean = $this->_validate($aData, $iCategoryId);

        db()->update(':hulahoot_profile_category', $aClean, ['category_id' => $iCategoryId]);

        return true;
    }

    /**
     * @param int $iCategoryId
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the row doesn't exist, is still referenced by any
     *         hulahoot_profile row, or (for a Category row) still has live Subcategory rows
     */
    public function delete($iCategoryId)
    {
        $iCategoryId = (int)$iCategoryId;
        $aExisting = $this->getById($iCategoryId);

        if (!$aExisting) {
            throw new \InvalidArgumentException('Category ' . $iCategoryId . ' does not exist.');
        }

        $iProfileCount = (int)db()->select('COUNT(*)')
            ->from(':hulahoot_profile')
            ->where('category_id = ' . $iCategoryId . ' OR subcategory_id = ' . $iCategoryId)
            ->execute('getSlaveField');

        if ($iProfileCount > 0) {
            throw new \InvalidArgumentException(
                'Cannot delete - still in use by ' . $iProfileCount . ' existing profile(s).'
            );
        }

        if ((int)$aExisting['parent_id'] === 0) {
            $iChildCount = (int)db()->select('COUNT(*)')
                ->from(':hulahoot_profile_category')
                ->where(['parent_id' => $iCategoryId])
                ->execute('getSlaveField');

            if ($iChildCount > 0) {
                throw new \InvalidArgumentException(
                    'Cannot delete - this category still has ' . $iChildCount . ' subcategor(y/ies) under it. Delete or reassign those first.'
                );
            }
        }

        db()->delete(':hulahoot_profile_category', ['category_id' => $iCategoryId]);

        return true;
    }

    /**
     * Shared create()/update() field validation. Returns the clean,
     * DB-ready field array.
     *
     * @param array $aData
     * @param int|null $iExcludeId when editing, this row's own id is excluded from
     *        both the name_url uniqueness check and the self-parenting check
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    private function _validate(array $aData, $iExcludeId)
    {
        $iProfileTypeId = (int)($aData['profile_type_id'] ?? 0);
        if ($iProfileTypeId <= 0) {
            throw new \InvalidArgumentException('A Profile Type is required.');
        }

        $aProfileType = db()->select('profile_type_id')
            ->from(':hulahoot_profile_type')
            ->where(['profile_type_id' => $iProfileTypeId])
            ->execute('getSlaveRow');

        if (!$aProfileType) {
            throw new \InvalidArgumentException('Profile type ' . $iProfileTypeId . ' does not exist.');
        }

        $iParentId = (int)($aData['parent_id'] ?? 0);

        if ($iParentId !== 0) {
            if ($iExcludeId !== null && $iParentId === (int)$iExcludeId) {
                throw new \InvalidArgumentException('A category cannot be its own parent.');
            }

            $aParent = $this->getById($iParentId);

            if (!$aParent
                || (int)$aParent['parent_id'] !== 0
                || (int)$aParent['profile_type_id'] !== $iProfileTypeId
            ) {
                throw new \InvalidArgumentException(
                    'Parent ' . $iParentId . ' is not a valid top-level category for this profile type.'
                );
            }
        }

        $sName = trim((string)($aData['name'] ?? ''));
        if ($sName === '') {
            throw new \InvalidArgumentException('Name is required.');
        }
        if (function_exists('mb_strlen') ? mb_strlen($sName) > 100 : strlen($sName) > 100) {
            throw new \InvalidArgumentException('Name must be 100 characters or fewer.');
        }

        $sNameUrl = trim((string)($aData['name_url'] ?? ''));
        if ($sNameUrl !== '') {
            if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $sNameUrl)) {
                throw new \InvalidArgumentException('Slug may only contain lowercase letters, numbers, and hyphens.');
            }
            if (strlen($sNameUrl) > 100) {
                throw new \InvalidArgumentException('Slug must be 100 characters or fewer.');
            }

            // Unique within profile_type_id, not globally - the same slug
            // (e.g. "retail") can validly exist under two different types.
            $iExistingId = db()->select('category_id')
                ->from(':hulahoot_profile_category')
                ->where(['profile_type_id' => $iProfileTypeId, 'name_url' => $sNameUrl])
                ->execute('getSlaveField');

            if ($iExistingId && (int)$iExistingId !== (int)$iExcludeId) {
                throw new \InvalidArgumentException('That slug is already in use by another category under this profile type.');
            }
        } else {
            $sNameUrl = null;
        }

        return [
            'profile_type_id' => $iProfileTypeId,
            'parent_id' => $iParentId,
            'name' => $sName,
            'name_url' => $sNameUrl,
            'is_active' => !empty($aData['is_active']) ? 1 : 0,
            'ordering' => (int)($aData['ordering'] ?? 0),
        ];
    }
}
