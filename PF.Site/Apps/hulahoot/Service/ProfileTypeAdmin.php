<?php

namespace Apps\Hulahoot\Service;

/**
 * Class ProfileTypeAdmin
 *
 * AdminCP-only CRUD for hulahoot_profile_type. Kept separate from
 * Service\Profile (which owns profile *assignment* - creating/reading a
 * Master Account's own hulahoot_profile rows) so that class's existing,
 * already-exercised read paths (getProfileType(), getActiveProfileTypes(),
 * etc.) are never touched by this admin-only write surface - zero risk of
 * regressing registration, /my-profiles/*, or anything else that already
 * depends on Service\Profile. This class reads through that same table via
 * its own queries; nothing here bypasses the existing invariants
 * (is_default exactly-one, no DB-level uniqueness) documented in
 * Installation/Database/ProfileType.php and Service/Profile.php.
 *
 * Every public method here is meant to be called only from an
 * admincp/hulahoot/* route, after the caller has already checked
 * admincp.has_admin_access and the relevant hulahoot.can_* permission -
 * this class does not check permissions itself, matching how phpFox's own
 * AdminCP services (e.g. Admincp_Service_Setting) work.
 *
 * @package Apps\Hulahoot\Service
 */
class ProfileTypeAdmin
{
    /**
     * Every Profile Type, active or not, for the AdminCP browse screen -
     * plus a computed count of how many hulahoot_profile rows currently use
     * each one, matching the "counts computed on read, not stored"
     * convention already used elsewhere in this module.
     *
     * @return array
     */
    public function listAll()
    {
        $aTypes = (array)db()->select('*')
            ->from(':hulahoot_profile_type')
            ->order('ordering ASC, profile_type_id ASC')
            ->execute('getSlaveRows');

        foreach ($aTypes as &$aType) {
            $aType['profile_count'] = (int)db()->select('COUNT(*)')
                ->from(':hulahoot_profile')
                ->where(['profile_type_id' => (int)$aType['profile_type_id']])
                ->execute('getSlaveField');
        }
        unset($aType);

        return $aTypes;
    }

    /**
     * Fetch one row regardless of is_active - unlike
     * Service\Profile::getProfileType(), which only returns active rows
     * (correct for its own callers, wrong for an admin edit screen that
     * must still be able to open and re-activate an inactive type).
     *
     * @param int $iProfileTypeId
     *
     * @return array|false
     */
    public function getById($iProfileTypeId)
    {
        return db()->select('*')
            ->from(':hulahoot_profile_type')
            ->where(['profile_type_id' => (int)$iProfileTypeId])
            ->execute('getSlaveRow');
    }

    /**
     * @param array $aData name, name_url, description, icon, requires_category,
     *        is_default, is_individual, is_active, is_user_creatable, ordering
     *
     * @return int the new profile_type_id
     *
     * @throws \InvalidArgumentException on any validation failure (see _validate())
     */
    public function create(array $aData)
    {
        $aClean = $this->_validate($aData, null);

        // New types always append after the last one, regardless of
        // whatever the (now-secondary, drag-and-drop is primary) Sort
        // Order field on the form was left at - otherwise every new type
        // defaults to ordering=0 and jumps to the very front of both the
        // AdminCP list and the registration dropdown.
        $aClean['ordering'] = 1 + (int)db()->select('MAX(ordering)')
            ->from(':hulahoot_profile_type')
            ->execute('getSlaveField');

        if ($aClean['is_default']) {
            db()->beginTransaction();

            try {
                $this->_clearDefault();

                $iId = db()->insert(':hulahoot_profile_type', array_merge($aClean, [
                    'created' => time(),
                ]));

                db()->commit();
            } catch (\Exception $e) {
                db()->rollback();
                throw $e;
            }

            return (int)$iId;
        }

        return (int)db()->insert(':hulahoot_profile_type', array_merge($aClean, [
            'created' => time(),
        ]));
    }

    /**
     * @param int $iProfileTypeId
     * @param array $aData same fields as create()
     *
     * @return bool
     *
     * @throws \InvalidArgumentException on any validation failure, including the two
     *         default-row guards described in _validate()
     */
    public function update($iProfileTypeId, array $aData)
    {
        $iProfileTypeId = (int)$iProfileTypeId;
        $aExisting = $this->getById($iProfileTypeId);

        if (!$aExisting) {
            throw new \InvalidArgumentException('Profile type ' . $iProfileTypeId . ' does not exist.');
        }

        $aClean = $this->_validate($aData, $iProfileTypeId);

        $bWasDefault = (int)$aExisting['is_default'] === 1;

        if ($bWasDefault && !$aClean['is_default']) {
            throw new \InvalidArgumentException(
                'Cannot remove default status from this type directly - set a different type as default instead, which unsets this one automatically.'
            );
        }

        if ($aClean['is_default'] && !$bWasDefault) {
            db()->beginTransaction();

            try {
                $this->_clearDefault();

                db()->update(':hulahoot_profile_type', $aClean, ['profile_type_id' => $iProfileTypeId]);

                db()->commit();
            } catch (\Exception $e) {
                db()->rollback();
                throw $e;
            }

            return true;
        }

        db()->update(':hulahoot_profile_type', $aClean, ['profile_type_id' => $iProfileTypeId]);

        return true;
    }

    /**
     * @param int $iProfileTypeId
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the row doesn't exist, is the current default,
     *         or is still referenced by any hulahoot_profile / hulahoot_profile_category row
     */
    public function delete($iProfileTypeId)
    {
        $iProfileTypeId = (int)$iProfileTypeId;
        $aExisting = $this->getById($iProfileTypeId);

        if (!$aExisting) {
            throw new \InvalidArgumentException('Profile type ' . $iProfileTypeId . ' does not exist.');
        }

        if ((int)$aExisting['is_default'] === 1) {
            throw new \InvalidArgumentException(
                'Cannot delete the default type - set a different type as default first.'
            );
        }

        $iProfileCount = (int)db()->select('COUNT(*)')
            ->from(':hulahoot_profile')
            ->where(['profile_type_id' => $iProfileTypeId])
            ->execute('getSlaveField');

        if ($iProfileCount > 0) {
            throw new \InvalidArgumentException(
                'Cannot delete - still in use by ' . $iProfileCount . ' existing profile(s).'
            );
        }

        $iCategoryCount = (int)db()->select('COUNT(*)')
            ->from(':hulahoot_profile_category')
            ->where(['profile_type_id' => $iProfileTypeId])
            ->execute('getSlaveField');

        if ($iCategoryCount > 0) {
            throw new \InvalidArgumentException(
                'Cannot delete - this type still has ' . $iCategoryCount . ' categor(y/ies) under it. Delete or reassign those first.'
            );
        }

        db()->delete(':hulahoot_profile_type', ['profile_type_id' => $iProfileTypeId]);

        return true;
    }

    /**
     * Shared create()/update() field validation. Returns the clean,
     * DB-ready field array (never the raw input) so callers can never
     * accidentally insert/update an unvalidated field.
     *
     * @param array $aData
     * @param int|null $iExcludeId when editing, this row's own id is excluded
     *        from the name_url uniqueness check
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

        $sNameUrl = trim((string)($aData['name_url'] ?? ''));
        if ($sNameUrl !== '') {
            if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $sNameUrl)) {
                throw new \InvalidArgumentException('Slug may only contain lowercase letters, numbers, and hyphens.');
            }
            if (strlen($sNameUrl) > 100) {
                throw new \InvalidArgumentException('Slug must be 100 characters or fewer.');
            }

            $oExistsQuery = db()->select('profile_type_id')
                ->from(':hulahoot_profile_type')
                ->where(['name_url' => $sNameUrl]);
            $iExistingId = $oExistsQuery->execute('getSlaveField');

            if ($iExistingId && (int)$iExistingId !== (int)$iExcludeId) {
                throw new \InvalidArgumentException('That slug is already in use by another profile type.');
            }
        } else {
            $sNameUrl = null;
        }

        $sDescription = trim((string)($aData['description'] ?? ''));
        if ($sDescription !== '' && strlen($sDescription) > 255) {
            throw new \InvalidArgumentException('Description must be 255 characters or fewer.');
        }

        $bIsActive = !empty($aData['is_active']);
        $bIsDefault = !empty($aData['is_default']);

        if ($bIsDefault && !$bIsActive) {
            throw new \InvalidArgumentException('The default type cannot be inactive - it must remain active, or a different type must be made default first.');
        }

        return [
            'name' => $sName,
            'name_url' => $sNameUrl,
            'description' => $sDescription !== '' ? $sDescription : null,
            'icon' => trim((string)($aData['icon'] ?? '')) ?: null,
            'requires_category' => !empty($aData['requires_category']) ? 1 : 0,
            'is_default' => $bIsDefault ? 1 : 0,
            'is_individual' => !empty($aData['is_individual']) ? 1 : 0,
            'is_active' => $bIsActive ? 1 : 0,
            'is_user_creatable' => !empty($aData['is_user_creatable']) ? 1 : 0,
            'ordering' => (int)($aData['ordering'] ?? 0),
        ];
    }

    /**
     * @return void
     */
    private function _clearDefault()
    {
        db()->update(':hulahoot_profile_type', ['is_default' => 0], ['is_default' => 1]);
    }
}
