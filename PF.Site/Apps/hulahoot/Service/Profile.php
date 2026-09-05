<?php

namespace Apps\Hulahoot\Service;

/**
 * Class Profile
 *
 * Creates and retrieves hulahoot_profile records. This is the only
 * supported way application code should read or write that table.
 *
 * A phpFox account (phpfox_user - the "Master Account") may hold more
 * than one profile: exactly one profile of an individual-classification
 * type (Personal, Creator, Influencer, ...) created at registration -
 * whichever the user picked in the registration form - and any number of
 * additional profiles of any type, individual or organization (Business,
 * Organization, or any future administrator-defined type) created later
 * through the profile management system (PF.Site/Apps/hulahoot/start.php).
 * See docs/ArchitectureComplianceReport.md for the full rationale.
 *
 * The one invariant enforced here (not by the database): at most one of
 * a user's profiles is ever primary ("currently active") at a time. That
 * is enforced in create() and setPrimary(), the same way
 * hulahoot_profile_type's "exactly one is_default row" invariant is
 * enforced by Installation\Seed\ProfileTypeSeeder rather than a database
 * constraint - see the note in Installation/Database/Profile.php for why
 * this schema has no UNIQUE-key facility to lean on instead.
 *
 * Nothing in this class hard-codes which profile types exist - "the
 * default type" and "does this type require a category" are always
 * looked up from hulahoot_profile_type, never assumed, so administrator-
 * defined types work without any change here.
 *
 * @package Apps\Hulahoot\Service
 */
class Profile
{
    /**
     * Create a profile for a user. A brand-new account's first profile
     * always becomes primary, regardless of $bMakePrimary. Every profile
     * after that defaults to non-primary unless the caller explicitly
     * asks to switch (pass $bMakePrimary = true), so creating a second
     * or third profile never silently steals "active" status from the
     * one the user is currently using.
     *
     * No limit is enforced here on how many profiles a user may hold, or
     * how many of a given type - this layer only guarantees the data is
     * well-formed. Whether/when a Master Account is allowed to create
     * another profile (free tier limits, paid entitlements, admin
     * overrides) is the future entitlement system's responsibility, not
     * this method's - see docs/FutureExtensionGuide.md.
     *
     * @param int $iUserId phpfox_user.user_id (the Master Account)
     * @param int $iProfileTypeId hulahoot_profile_type.profile_type_id
     * @param int|null $iCategoryId hulahoot_profile_category.category_id (a parent_id = 0 row)
     * @param int|null $iSubcategoryId hulahoot_profile_category.category_id (a child of $iCategoryId)
     * @param bool|null $bMakePrimary null = auto (primary only if this is the user's first profile)
     * @param string|null $sProfileName optional display label (e.g. "Joe's
     *        Pizza") shown on profile cards; falls back to the Profile
     *        Type's own name when blank. Trimmed and capped at 100 chars
     *        (hulahoot_profile.profile_name's column width).
     *
     * @return int the new profile_id
     *
     * @throws \InvalidArgumentException if $iUserId is invalid, the profile type doesn't exist,
     *         the type requires a category that wasn't provided, or the category/subcategory
     *         given don't actually belong to the given type/category
     */
    public function create($iUserId, $iProfileTypeId, $iCategoryId = null, $iSubcategoryId = null, $bMakePrimary = null, $sProfileName = null)
    {
        $iUserId = (int)$iUserId;
        $iProfileTypeId = (int)$iProfileTypeId;
        $sProfileName = self::_normalizeProfileName($sProfileName);

        if ($iUserId <= 0) {
            throw new \InvalidArgumentException('create() requires a valid user_id.');
        }

        $aProfileType = $this->getProfileType($iProfileTypeId);
        if (!$aProfileType) {
            throw new \InvalidArgumentException(
                'Profile type ' . $iProfileTypeId . ' does not exist or is not active.'
            );
        }

        // No restriction here on individual vs organization classification,
        // or on is_default, or on how many profiles of a type a user may
        // already hold - this layer only validates structural correctness
        // ("does this type/category/subcategory combination make sense").
        // WHERE each classification is offered (registration only shows
        // is_individual = 1 types - see hooks/user.template_default_block_
        // register_step1_4.php - the post-registration create-profile flow
        // shows everything active) is a UI-layer decision, and WHETHER an
        // account is currently allowed to create another profile at all is
        // the future entitlement system's job - see docs/FutureExtensionGuide.md.

        if ((int)$aProfileType['requires_category'] === 1 && empty($iCategoryId)) {
            throw new \InvalidArgumentException(
                'Profile type "' . _p($aProfileType['name']) . '" requires a category.'
            );
        }

        if ($iCategoryId) {
            $iCategoryId = (int)$iCategoryId;
            $aCategory = $this->getCategory($iCategoryId);
            if (!$aCategory || (int)$aCategory['parent_id'] !== 0
                || (int)$aCategory['profile_type_id'] !== $iProfileTypeId
            ) {
                throw new \InvalidArgumentException(
                    'Category ' . $iCategoryId . ' is not a valid top-level category for profile type ' . $iProfileTypeId . '.'
                );
            }
        }

        if ($iSubcategoryId) {
            $iSubcategoryId = (int)$iSubcategoryId;
            $aSubcategory = $this->getCategory($iSubcategoryId);
            if (!$aSubcategory || (int)$aSubcategory['parent_id'] !== (int)$iCategoryId) {
                throw new \InvalidArgumentException(
                    'Subcategory ' . $iSubcategoryId . ' is not a valid child of category ' . $iCategoryId . '.'
                );
            }
        }

        $bHasExistingProfile = $this->userHasProfile($iUserId);

        if ($bMakePrimary === null) {
            $bMakePrimary = !$bHasExistingProfile;
        }

        $iNow = time();

        // Clearing the previous primary and inserting the new row happen
        // in one transaction when this profile is meant to become primary
        // - see the note on setPrimary() below for why this matters once
        // switching is a real, concurrent, user-driven action.
        $bNeedsTransaction = $bMakePrimary && $bHasExistingProfile;

        if ($bNeedsTransaction) {
            db()->beginTransaction();
        }

        try {
            if ($bNeedsTransaction) {
                $this->_clearPrimaryForUser($iUserId);
            }

            $iProfileId = db()->insert(':hulahoot_profile', [
                'user_id' => $iUserId,
                'profile_type_id' => $iProfileTypeId,
                'profile_name' => $sProfileName,
                'category_id' => $iCategoryId ?: null,
                'subcategory_id' => $iSubcategoryId ?: null,
                'is_primary' => $bMakePrimary ? 1 : 0,
                'is_active' => 1,
                'created' => $iNow,
                'updated' => $iNow,
            ]);

            if ($bNeedsTransaction) {
                db()->commit();
            }
        } catch (\Exception $e) {
            if ($bNeedsTransaction) {
                db()->rollback();
            }
            throw $e;
        }

        return (int)$iProfileId;
    }

    /**
     * Switch which of a user's profiles is currently active. The clear-
     * previous-primary and set-new-primary steps run inside a transaction
     * so a concurrent create()/setPrimary() call for the same user can
     * never leave the account with zero or two primary profiles - a real
     * possibility once this is called from a live, user-driven "switch
     * profile" control rather than nothing at all.
     *
     * @param int $iUserId
     * @param int $iProfileId
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the profile doesn't exist, isn't active,
     *         or doesn't belong to $iUserId
     */
    public function setPrimary($iUserId, $iProfileId)
    {
        $iUserId = (int)$iUserId;
        $iProfileId = (int)$iProfileId;

        $aProfile = $this->getById($iProfileId);
        if (!$aProfile || (int)$aProfile['user_id'] !== $iUserId) {
            throw new \InvalidArgumentException(
                'Profile ' . $iProfileId . ' does not belong to user ' . $iUserId . '.'
            );
        }

        if ((int)$aProfile['is_active'] !== 1) {
            throw new \InvalidArgumentException('Profile ' . $iProfileId . ' is not active.');
        }

        if ((int)$aProfile['is_primary'] === 1) {
            return true;
        }

        db()->beginTransaction();

        try {
            $this->_clearPrimaryForUser($iUserId);

            db()->update(':hulahoot_profile', [
                'is_primary' => 1,
                'updated' => time(),
            ], ['profile_id' => $iProfileId]);

            db()->commit();
        } catch (\Exception $e) {
            db()->rollback();
            throw $e;
        }

        return true;
    }

    /**
     * All profile rows owned by a user - Individual, plus any Business/
     * Organization/future-type profiles created after registration.
     *
     * @param int $iUserId
     *
     * @return array
     */
    public function getByUserId($iUserId)
    {
        return (array)db()->select('*')
            ->from(':hulahoot_profile')
            ->where(['user_id' => (int)$iUserId])
            ->order('profile_id ASC')
            ->execute('getSlaveRows');
    }

    /**
     * The user's currently-active profile (is_primary = 1).
     *
     * @param int $iUserId
     *
     * @return array|false
     */
    public function getPrimaryByUserId($iUserId)
    {
        return db()->select('*')
            ->from(':hulahoot_profile')
            ->where(['user_id' => (int)$iUserId, 'is_primary' => 1])
            ->execute('getSlaveRow');
    }

    /**
     * @param int $iProfileId
     *
     * @return array|false
     */
    public function getById($iProfileId)
    {
        return db()->select('*')
            ->from(':hulahoot_profile')
            ->where(['profile_id' => (int)$iProfileId])
            ->execute('getSlaveRow');
    }

    /**
     * @param int $iUserId
     *
     * @return bool
     */
    public function userHasProfile($iUserId)
    {
        $iCount = db()->select('COUNT(*)')
            ->from(':hulahoot_profile')
            ->where(['user_id' => (int)$iUserId])
            ->execute('getSlaveField');

        return $iCount > 0;
    }

    /**
     * The profile_type_id an unspecified/new signup should use
     * (hulahoot_profile_type.is_default = 1). Individual, at launch -
     * looked up, never hard-coded, so this keeps working unchanged if an
     * administrator ever changes which type is the default.
     *
     * @return int|null
     */
    public function getDefaultProfileTypeId()
    {
        $iId = db()->select('profile_type_id')
            ->from(':hulahoot_profile_type')
            ->where(['is_default' => 1, 'is_active' => 1])
            ->order('profile_type_id ASC')
            ->execute('getSlaveField');

        return $iId ? (int)$iId : null;
    }

    /**
     * Change an existing profile's name and, for types that use one, its
     * category/subcategory. Individual profiles (requires_category = 0)
     * only take $sProfileName - $iCategoryId/$iSubcategoryId are ignored
     * for them rather than rejected, so the same form/route can handle
     * every profile type without branching in the caller.
     *
     * @param int $iUserId
     * @param int $iProfileId
     * @param int|null $iCategoryId ignored for types that don't require a category
     * @param int|null $iSubcategoryId ignored for types that don't require a category
     * @param string|null $sProfileName pass null to leave the current name unchanged;
     *        pass '' to clear it back to the Profile Type's default display name
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the profile doesn't belong to $iUserId, the type
     *         requires a category that wasn't provided, or the category/subcategory given
     *         are invalid for it
     */
    public function update($iUserId, $iProfileId, $iCategoryId = null, $iSubcategoryId = null, $sProfileName = null)
    {
        $iUserId = (int)$iUserId;
        $iProfileId = (int)$iProfileId;

        $aProfile = $this->getById($iProfileId);
        if (!$aProfile || (int)$aProfile['user_id'] !== $iUserId) {
            throw new \InvalidArgumentException(
                'Profile ' . $iProfileId . ' does not belong to user ' . $iUserId . '.'
            );
        }

        $iProfileTypeId = (int)$aProfile['profile_type_id'];
        $aProfileType = $this->getProfileType($iProfileTypeId);

        $aUpdate = ['updated' => time()];

        if ($sProfileName !== null) {
            $aUpdate['profile_name'] = self::_normalizeProfileName($sProfileName);
        }

        if ($aProfileType && (int)$aProfileType['requires_category'] === 1) {
            if (empty($iCategoryId)) {
                throw new \InvalidArgumentException('A category is required.');
            }

            $iCategoryId = (int)$iCategoryId;
            $aCategory = $this->getCategory($iCategoryId);
            if (!$aCategory || (int)$aCategory['parent_id'] !== 0
                || (int)$aCategory['profile_type_id'] !== $iProfileTypeId
            ) {
                throw new \InvalidArgumentException(
                    'Category ' . $iCategoryId . ' is not a valid top-level category for this profile.'
                );
            }

            if ($iSubcategoryId) {
                $iSubcategoryId = (int)$iSubcategoryId;
                $aSubcategory = $this->getCategory($iSubcategoryId);
                if (!$aSubcategory || (int)$aSubcategory['parent_id'] !== $iCategoryId) {
                    throw new \InvalidArgumentException(
                        'Subcategory ' . $iSubcategoryId . ' is not a valid child of category ' . $iCategoryId . '.'
                    );
                }
            }

            $aUpdate['category_id'] = $iCategoryId;
            $aUpdate['subcategory_id'] = $iSubcategoryId ?: null;
        }

        db()->update(':hulahoot_profile', $aUpdate, ['profile_id' => $iProfileId]);

        return true;
    }

    /**
     * @param string|null $sName
     *
     * @return string|null
     */
    private static function _normalizeProfileName($sName)
    {
        if ($sName === null) {
            return null;
        }

        $sName = trim($sName);

        if ($sName === '') {
            return null;
        }

        // Matches hulahoot_profile.profile_name's column width.
        if (function_exists('mb_substr')) {
            $sName = mb_substr($sName, 0, 100);
        } else {
            $sName = substr($sName, 0, 100);
        }

        return $sName;
    }

    /**
     * All active profile types (individual and organization alike), in
     * admin-defined order. Used to populate the post-registration
     * "create a profile" type picker. Registration itself never calls
     * this - it uses getActiveIndividualProfileTypes() instead, since
     * only individual-classification types are offered there.
     *
     * @return array
     */
    public function getActiveProfileTypes()
    {
        return (array)db()->select('*')
            ->from(':hulahoot_profile_type')
            ->where(['is_active' => 1])
            ->order('ordering ASC')
            ->execute('getSlaveRows');
    }

    /**
     * The types a Master Account may pick for themselves via
     * /my-profiles/create - active AND is_user_creatable = 1. Used instead
     * of getActiveProfileTypes() there so an administrator's "User
     * Creatable" toggle (AdminCP Profile Types screen) actually has an
     * effect; registration is unaffected either way, since it always uses
     * getActiveIndividualProfileTypes() below, not this method.
     *
     * @return array
     */
    public function getUserCreatableProfileTypes()
    {
        return (array)db()->select('*')
            ->from(':hulahoot_profile_type')
            ->where(['is_active' => 1, 'is_user_creatable' => 1])
            ->order('ordering ASC')
            ->execute('getSlaveRows');
    }

    /**
     * The individual-classification types (Personal, Creator, Influencer,
     * Celebrity, Public Figure, and any other administrator-approved
     * individual type) - the only types registration is allowed to offer.
     *
     * @return array
     */
    public function getActiveIndividualProfileTypes()
    {
        return (array)db()->select('*')
            ->from(':hulahoot_profile_type')
            ->where(['is_active' => 1, 'is_individual' => 1])
            ->order('ordering ASC')
            ->execute('getSlaveRows');
    }

    /**
     * Whether $iProfileTypeId is a currently-active, individual-
     * classification type - used to validate a submitted registration
     * choice before trusting it.
     *
     * @param int $iProfileTypeId
     *
     * @return bool
     */
    public function isActiveIndividualProfileType($iProfileTypeId)
    {
        $aType = $this->getProfileType((int)$iProfileTypeId);

        return $aType && (int)$aType['is_individual'] === 1;
    }

    /**
     * Top-level categories (parent_id = 0) for a profile type.
     *
     * @param int $iProfileTypeId
     *
     * @return array
     */
    public function getTopCategoriesForType($iProfileTypeId)
    {
        return (array)db()->select('*')
            ->from(':hulahoot_profile_category')
            ->where(['profile_type_id' => (int)$iProfileTypeId, 'parent_id' => 0, 'is_active' => 1])
            ->order('ordering ASC')
            ->execute('getSlaveRows');
    }

    /**
     * Subcategories (children) of a given category.
     *
     * @param int $iCategoryId
     *
     * @return array
     */
    public function getSubcategories($iCategoryId)
    {
        return (array)db()->select('*')
            ->from(':hulahoot_profile_category')
            ->where(['parent_id' => (int)$iCategoryId, 'is_active' => 1])
            ->order('ordering ASC')
            ->execute('getSlaveRows');
    }

    /**
     * Same as getByUserId(), with each row's profile type/category/
     * subcategory names attached for display - saves every caller that
     * needs to render a list from re-implementing the same three lookups.
     *
     * @param int $iUserId
     *
     * @return array
     */
    public function getByUserIdWithDetails($iUserId)
    {
        $aProfiles = $this->getByUserId($iUserId);

        if (!$aProfiles) {
            return $aProfiles;
        }

        // Both reference tables are small and mostly static, so it's
        // cheaper to fetch each once and look up in memory than to run
        // getProfileType()/getCategory() per profile row (an N+1 pattern
        // that used to run here for every profile the user owns).
        $aTypesById = [];
        foreach ($this->getActiveProfileTypes() as $aType) {
            $aTypesById[(int)$aType['profile_type_id']] = $aType;
        }

        $aCategoriesById = [];
        foreach ((array)db()->select('*')->from(':hulahoot_profile_category')->where(['is_active' => 1])->execute('getSlaveRows') as $aCategory) {
            $aCategoriesById[(int)$aCategory['category_id']] = $aCategory;
        }

        foreach ($aProfiles as &$aProfile) {
            $aType = isset($aTypesById[(int)$aProfile['profile_type_id']]) ? $aTypesById[(int)$aProfile['profile_type_id']] : null;
            $aProfile['profile_type_name'] = $aType ? $aType['name'] : null;

            // What a profile card's title shows - the user's own label if
            // they set one, otherwise the Profile Type's own name so a
            // card is never blank.
            $aProfile['display_name'] = !empty($aProfile['profile_name'])
                ? $aProfile['profile_name']
                : ($aType ? _p($aType['name']) : null);

            $aProfile['category_name'] = null;
            if (!empty($aProfile['category_id']) && isset($aCategoriesById[(int)$aProfile['category_id']])) {
                $aProfile['category_name'] = $aCategoriesById[(int)$aProfile['category_id']]['name'];
            }

            $aProfile['subcategory_name'] = null;
            if (!empty($aProfile['subcategory_id']) && isset($aCategoriesById[(int)$aProfile['subcategory_id']])) {
                $aProfile['subcategory_name'] = $aCategoriesById[(int)$aProfile['subcategory_id']]['name'];
            }
        }
        unset($aProfile);

        return $aProfiles;
    }

    /**
     * @param int $iUserId
     *
     * @return void
     */
    private function _clearPrimaryForUser($iUserId)
    {
        db()->update(':hulahoot_profile', [
            'is_primary' => 0,
            'updated' => time(),
        ], ['user_id' => (int)$iUserId, 'is_primary' => 1]);
    }

    /**
     * @param int $iProfileTypeId
     *
     * @return array|false
     */
    public function getProfileType($iProfileTypeId)
    {
        return db()->select('*')
            ->from(':hulahoot_profile_type')
            ->where(['profile_type_id' => (int)$iProfileTypeId, 'is_active' => 1])
            ->execute('getSlaveRow');
    }

    /**
     * The current profile-type label for a user's primary profile (e.g.
     * "Personal", "Creator", "Public Figure"), already resolved through
     * _p(). Used anywhere phpFox would otherwise show the raw
     * phpfox_user_group title ("Registered User") - that group name is
     * the same for every individual account regardless of which Profile
     * Type they picked, so it isn't a useful membership label on its own.
     * Returns null (never throws) if the user has no hulahoot profile or
     * the type record is missing/inactive, so callers can fall back to
     * whatever they'd otherwise display.
     *
     * @param int $iUserId
     *
     * @return string|null
     */
    public function getPrimaryProfileTypeLabel($iUserId)
    {
        $aProfile = $this->getPrimaryByUserId((int)$iUserId);
        if (empty($aProfile) || empty($aProfile['profile_type_id'])) {
            return null;
        }

        $aType = $this->getProfileType((int)$aProfile['profile_type_id']);
        if (empty($aType) || empty($aType['name'])) {
            return null;
        }

        return _p($aType['name']);
    }

    /**
     * @param int $iCategoryId
     *
     * @return array|false
     */
    public function getCategory($iCategoryId)
    {
        return db()->select('*')
            ->from(':hulahoot_profile_category')
            ->where(['category_id' => (int)$iCategoryId, 'is_active' => 1])
            ->execute('getSlaveRow');
    }

    /**
     * Category/subcategory maps for the create/edit forms' client-side
     * cascading dropdowns (see assets/profile-form.js) - built from the
     * same category table the server-side validation in create()/update()
     * uses, so the JS filtering and that validation can never disagree
     * about what's a valid combination.
     *
     * @return array [categoriesByType, subcategoriesByCategory], each
     *         keyed by profile_type_id / category_id, valued as a list of
     *         ['id' => int, 'name' => already-_p()'d string]
     */
    public function getCategoryMapsForJs()
    {
        $categoriesByType = [];
        $subcategoriesByCategory = [];

        foreach ($this->getActiveProfileTypes() as $aType) {
            if (!$aType['requires_category']) {
                continue;
            }

            $aTop = $this->getTopCategoriesForType((int)$aType['profile_type_id']);
            $categoriesByType[(int)$aType['profile_type_id']] = array_map(function ($aCategory) {
                return ['id' => (int)$aCategory['category_id'], 'name' => _p($aCategory['name'])];
            }, $aTop);

            foreach ($aTop as $aCategory) {
                $aSub = $this->getSubcategories((int)$aCategory['category_id']);
                if ($aSub) {
                    $subcategoriesByCategory[(int)$aCategory['category_id']] = array_map(function ($aSubcategory) {
                        return ['id' => (int)$aSubcategory['category_id'], 'name' => _p($aSubcategory['name'])];
                    }, $aSub);
                }
            }
        }

        return [$categoriesByType, $subcategoriesByCategory];
    }
}
