<?php

namespace Apps\Hulahoot\Service;

/**
 * Class Swess
 *
 * The single place application code asks "is SWESS available to this
 * user, and specifically what may they do." Every AdminCP screen that
 * manages the whitelist, and any future caller (the eventual Hulahoot.com
 * composer, or whatever Partner-Portal endpoint it talks to) that needs
 * to enforce it, goes through here - never a direct query against
 * hulahoot_swess_* from anywhere else, same convention as Service\Profile
 * owning hulahoot_profile.
 *
 * Deliberately reuses, and never duplicates:
 * - Service\Profile for the "self" identity (hulahoot_profile)
 * - native phpFox Pages for the "business" identity - this class never
 *   creates, owns, or modifies a Page; it only records that a specific,
 *   already-existing Page has been approved
 * - Service\Entitlement / Core Subscriptions for subscription/credit
 *   state - not referenced here at all; entitlement is a separate
 *   question from "is this identity whitelisted," and the two are meant
 *   to be checked independently by whatever future caller needs both
 *
 * canPostAs() is the enforcement method the whole SWESS foundation exists
 * to prove: it must return the same answer whether asked about a normal
 * profile, a staff profile (which is just a normal profile - the client's
 * own clarification), or a business Page, purely from what's actually
 * configured in these tables - never a hardcoded special case for any
 * particular kind of account.
 *
 * @package Apps\Hulahoot\Service
 */
class Swess
{
    // ---- Whitelist -----------------------------------------------------

    /**
     * @param int $iUserId
     *
     * @return array|false the hulahoot_swess_whitelist row, or false if
     *         this user has never been whitelisted
     */
    public function getWhitelistForUser($iUserId)
    {
        return db()->select('*')
            ->from(':hulahoot_swess_whitelist')
            ->where(['user_id' => (int)$iUserId])
            ->execute('getSlaveRow');
    }

    /**
     * @param int $iWhitelistId
     *
     * @return array|false
     */
    public function getWhitelistById($iWhitelistId)
    {
        return db()->select('*')
            ->from(':hulahoot_swess_whitelist')
            ->where(['whitelist_id' => (int)$iWhitelistId])
            ->execute('getSlaveRow');
    }

    /**
     * Every whitelist row, newest first, with the owning account's
     * username attached for the AdminCP list - saves that screen from
     * re-implementing the join.
     *
     * @return array
     */
    public function listWhitelist()
    {
        return (array)db()->select('w.*, u.user_name, u.full_name')
            ->from(':hulahoot_swess_whitelist', 'w')
            ->join(':user', 'u', 'u.user_id = w.user_id')
            ->order('w.whitelist_id DESC')
            ->execute('getSlaveRows');
    }

    /**
     * Create or update the whitelist row for a user - a user has at most
     * one (enforced here, not by a database constraint, matching every
     * other single-row-per-user invariant in this app).
     *
     * @param int $iUserId
     * @param array $aData is_enabled, post_as_self, post_as_business
     * @param int|null $iActorUserId the admin making this change, for the
     *        audit log and enabled_by
     *
     * @return int the whitelist_id
     *
     * @throws \InvalidArgumentException if $iUserId doesn't resolve to a real account
     */
    public function setWhitelist($iUserId, array $aData, $iActorUserId = null)
    {
        $iUserId = (int)$iUserId;

        $aUser = db()->select('user_id')->from(':user')->where(['user_id' => $iUserId])->execute('getSlaveRow');
        if (!$aUser) {
            throw new \InvalidArgumentException('User ' . $iUserId . ' does not exist.');
        }

        $aClean = [
            'is_enabled' => !empty($aData['is_enabled']) ? 1 : 0,
            'post_as_self' => !empty($aData['post_as_self']) ? 1 : 0,
            'post_as_business' => !empty($aData['post_as_business']) ? 1 : 0,
        ];

        $aExisting = $this->getWhitelistForUser($iUserId);
        $iNow = time();

        if ($aExisting) {
            db()->update(':hulahoot_swess_whitelist', array_merge($aClean, [
                'updated' => $iNow,
            ]), ['whitelist_id' => (int)$aExisting['whitelist_id']]);

            $iWhitelistId = (int)$aExisting['whitelist_id'];
        } else {
            $iWhitelistId = (int)db()->insert(':hulahoot_swess_whitelist', array_merge($aClean, [
                'user_id' => $iUserId,
                'enabled_by' => $iActorUserId ?: null,
                'created' => $iNow,
                'updated' => $iNow,
            ]));
        }

        $this->logAudit($iUserId, null, null, $aClean['is_enabled'] ? 'whitelist_enabled' : 'whitelist_disabled', [
            'post_as_self' => $aClean['post_as_self'],
            'post_as_business' => $aClean['post_as_business'],
        ], $iActorUserId);

        return $iWhitelistId;
    }

    /**
     * Resolve a username or email to a user_id, for the AdminCP "add to
     * whitelist" form - deliberately a plain read against :user rather
     * than a native search service, since this only ever needs an exact
     * match on the two fields an admin would actually type.
     *
     * @param string $sUsernameOrEmail
     *
     * @return array|false {user_id, user_name, full_name, email}
     */
    public function findUserByUsernameOrEmail($sUsernameOrEmail)
    {
        $sValue = trim((string)$sUsernameOrEmail);

        if ($sValue === '') {
            return false;
        }

        $aUser = db()->select('user_id, user_name, full_name, email')
            ->from(':user')
            ->where(['user_name' => $sValue])
            ->execute('getSlaveRow');

        if ($aUser) {
            return $aUser;
        }

        return db()->select('user_id, user_name, full_name, email')
            ->from(':user')
            ->where(['email' => $sValue])
            ->execute('getSlaveRow');
    }

    // ---- Approved identities --------------------------------------------

    /**
     * @param int $iWhitelistId
     *
     * @return array each row plus its assigned tags (see getTagsForIdentity())
     */
    public function getApprovedIdentities($iWhitelistId)
    {
        $aRows = (array)db()->select('*')
            ->from(':hulahoot_swess_approved_identity')
            ->where(['whitelist_id' => (int)$iWhitelistId])
            ->order('approved_identity_id ASC')
            ->execute('getSlaveRows');

        foreach ($aRows as &$aRow) {
            $aRow['tags'] = $this->getTagsForIdentity((int)$aRow['approved_identity_id']);
        }
        unset($aRow);

        return $aRows;
    }

    /**
     * Approve one identity (the user's own "self" profile, or a specific
     * Page) for SWESS use. Does not check post_as_self/post_as_business
     * here - that's canPostAs()'s job at enforcement time, not a
     * creation-time restriction, so an admin can pre-configure an
     * identity before flipping the broader permission on.
     *
     * @param int $iWhitelistId
     * @param int $iUserId
     * @param string $sIdentityType 'self' | 'page'
     * @param int $iIdentityId profile_id for 'self', page_id for 'page'
     * @param int|null $iActorUserId
     *
     * @return int the approved_identity_id
     *
     * @throws \InvalidArgumentException if identity_type is invalid, or a 'self'
     *         identity is given that doesn't belong to $iUserId
     */
    public function approveIdentity($iWhitelistId, $iUserId, $sIdentityType, $iIdentityId, $iActorUserId = null)
    {
        $iWhitelistId = (int)$iWhitelistId;
        $iUserId = (int)$iUserId;
        $iIdentityId = (int)$iIdentityId;

        if (!in_array($sIdentityType, ['self', 'page'], true)) {
            throw new \InvalidArgumentException('identity_type must be "self" or "page".');
        }

        if ($sIdentityType === 'self') {
            // The user's own profile - never an arbitrary profile_id, so
            // this can't be used to approve someone else's identity by
            // mistake. Falls back to their primary profile when no
            // specific one is given.
            $aProfile = $iIdentityId
                ? (new Profile())->getById($iIdentityId)
                : (new Profile())->getPrimaryByUserId($iUserId);

            if (!$aProfile || (int)$aProfile['user_id'] !== $iUserId) {
                throw new \InvalidArgumentException('Profile does not belong to user ' . $iUserId . '.');
            }

            $iIdentityId = (int)$aProfile['profile_id'];
        }

        $aExisting = db()->select('approved_identity_id')
            ->from(':hulahoot_swess_approved_identity')
            ->where([
                'whitelist_id' => $iWhitelistId,
                'identity_type' => $sIdentityType,
                'identity_id' => $iIdentityId,
            ])
            ->execute('getSlaveRow');

        if ($aExisting) {
            db()->update(':hulahoot_swess_approved_identity', ['is_active' => 1], [
                'approved_identity_id' => (int)$aExisting['approved_identity_id'],
            ]);
            $iApprovedId = (int)$aExisting['approved_identity_id'];
        } else {
            $iApprovedId = (int)db()->insert(':hulahoot_swess_approved_identity', [
                'whitelist_id' => $iWhitelistId,
                'user_id' => $iUserId,
                'identity_type' => $sIdentityType,
                'identity_id' => $iIdentityId,
                'is_active' => 1,
                'approved_by' => $iActorUserId ?: null,
                'created' => time(),
            ]);
        }

        $this->logAudit($iUserId, $sIdentityType, $iIdentityId, 'identity_approved', [], $iActorUserId);

        return $iApprovedId;
    }

    /**
     * @param int $iApprovedIdentityId
     * @param int|null $iActorUserId
     *
     * @return void
     */
    public function revokeIdentity($iApprovedIdentityId, $iActorUserId = null)
    {
        $aIdentity = db()->select('*')
            ->from(':hulahoot_swess_approved_identity')
            ->where(['approved_identity_id' => (int)$iApprovedIdentityId])
            ->execute('getSlaveRow');

        if (!$aIdentity) {
            return;
        }

        db()->update(':hulahoot_swess_approved_identity', ['is_active' => 0], [
            'approved_identity_id' => (int)$iApprovedIdentityId,
        ]);

        $this->logAudit((int)$aIdentity['user_id'], $aIdentity['identity_type'], (int)$aIdentity['identity_id'], 'identity_revoked', [], $iActorUserId);
    }

    // ---- Tags ------------------------------------------------------------

    /**
     * @return array every tag, active or not, for the AdminCP list
     */
    public function listTags()
    {
        return (array)db()->select('*')
            ->from(':hulahoot_swess_tag')
            ->order('ordering ASC, tag_id ASC')
            ->execute('getSlaveRows');
    }

    /**
     * @return array active tags only, for the identity-tag picker
     */
    public function listActiveTags()
    {
        return (array)db()->select('*')
            ->from(':hulahoot_swess_tag')
            ->where(['is_active' => 1])
            ->order('ordering ASC, tag_id ASC')
            ->execute('getSlaveRows');
    }

    /**
     * @param int $iTagId
     *
     * @return array|false
     */
    public function getTagById($iTagId)
    {
        return db()->select('*')
            ->from(':hulahoot_swess_tag')
            ->where(['tag_id' => (int)$iTagId])
            ->execute('getSlaveRow');
    }

    /**
     * @param array $aData name, description, is_active, ordering
     *
     * @return int the new tag_id
     *
     * @throws \InvalidArgumentException if name is blank
     */
    public function createTag(array $aData)
    {
        $aClean = $this->_validateTag($aData);

        $aClean['ordering'] = 1 + (int)db()->select('MAX(ordering)')
            ->from(':hulahoot_swess_tag')
            ->execute('getSlaveField');

        return (int)db()->insert(':hulahoot_swess_tag', array_merge($aClean, [
            'created' => time(),
        ]));
    }

    /**
     * @param int $iTagId
     * @param array $aData same fields as createTag()
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the tag doesn't exist or name is blank
     */
    public function updateTag($iTagId, array $aData)
    {
        $iTagId = (int)$iTagId;

        if (!$this->getTagById($iTagId)) {
            throw new \InvalidArgumentException('Tag ' . $iTagId . ' does not exist.');
        }

        $aClean = $this->_validateTag($aData);
        unset($aClean['ordering']);

        db()->update(':hulahoot_swess_tag', $aClean, ['tag_id' => $iTagId]);

        return true;
    }

    /**
     * Which tags are allowed for one approved identity, ordered so the
     * default (if any) is first.
     *
     * @param int $iApprovedIdentityId
     *
     * @return array each row is the hulahoot_swess_tag row plus is_default
     */
    public function getTagsForIdentity($iApprovedIdentityId)
    {
        return (array)db()->select('t.*, it.is_default')
            ->from(':hulahoot_swess_identity_tag', 'it')
            ->join(':hulahoot_swess_tag', 't', 't.tag_id = it.tag_id')
            ->where(['it.approved_identity_id' => (int)$iApprovedIdentityId])
            ->order('it.is_default DESC, t.ordering ASC')
            ->execute('getSlaveRows');
    }

    /**
     * Allow one tag for one approved identity. If $bDefault is true,
     * every other tag already assigned to this identity is demoted first
     * - at most one default per identity, enforced here rather than by a
     * database constraint, matching every other such invariant in this
     * app.
     *
     * @param int $iApprovedIdentityId
     * @param int $iTagId
     * @param bool $bDefault
     *
     * @return void
     */
    public function assignTag($iApprovedIdentityId, $iTagId, $bDefault = false)
    {
        $iApprovedIdentityId = (int)$iApprovedIdentityId;
        $iTagId = (int)$iTagId;

        $aExisting = db()->select('id')
            ->from(':hulahoot_swess_identity_tag')
            ->where(['approved_identity_id' => $iApprovedIdentityId, 'tag_id' => $iTagId])
            ->execute('getSlaveRow');

        if ($aExisting) {
            return;
        }

        if ($bDefault) {
            db()->update(':hulahoot_swess_identity_tag', ['is_default' => 0], [
                'approved_identity_id' => $iApprovedIdentityId,
            ]);
        }

        db()->insert(':hulahoot_swess_identity_tag', [
            'approved_identity_id' => $iApprovedIdentityId,
            'tag_id' => $iTagId,
            'is_default' => $bDefault ? 1 : 0,
            'created' => time(),
        ]);

        $aIdentity = db()->select('user_id, identity_type, identity_id')
            ->from(':hulahoot_swess_approved_identity')
            ->where(['approved_identity_id' => $iApprovedIdentityId])
            ->execute('getSlaveRow');

        if ($aIdentity) {
            $this->logAudit((int)$aIdentity['user_id'], $aIdentity['identity_type'], (int)$aIdentity['identity_id'], 'tag_assigned', ['tag_id' => $iTagId]);
        }
    }

    /**
     * @param int $iApprovedIdentityId
     * @param int $iTagId
     *
     * @return void
     */
    public function unassignTag($iApprovedIdentityId, $iTagId)
    {
        db()->delete(':hulahoot_swess_identity_tag', [
            'approved_identity_id' => (int)$iApprovedIdentityId,
            'tag_id' => (int)$iTagId,
        ]);
    }

    // ---- Enforcement -------------------------------------------------

    /**
     * The one method every future caller (AdminCP preview, and eventually
     * the Hulahoot.com composer or whatever endpoint it talks to) should
     * use to answer "may this user post SWESS content as this identity."
     * Every check reads directly from these tables - nothing here is
     * cached, hardcoded, or special-cased by account type. A "staff
     * profile" and any other profile are checked identically, since a
     * staff profile is - per the client's own clarification - just a
     * normal profile with a whitelist entry.
     *
     * @param int $iUserId
     * @param string $sIdentityType 'self' | 'page'
     * @param int $iIdentityId
     *
     * @return array{allowed: bool, reason: string|null, tags: array}
     */
    public function canPostAs($iUserId, $sIdentityType, $iIdentityId)
    {
        $iUserId = (int)$iUserId;
        $iIdentityId = (int)$iIdentityId;

        $aWhitelist = $this->getWhitelistForUser($iUserId);

        $aResult = ['allowed' => false, 'reason' => null, 'tags' => []];

        // 'self' and 'page' are the only two identity types this
        // architecture defines (see hulahoot_swess_approved_identity's
        // own docblock) - checked symmetrically, no aliasing, since
        // nothing anywhere ever produces a third value.
        if (!in_array($sIdentityType, ['self', 'page'], true)) {
            $aResult['reason'] = 'invalid_identity_type';
        } elseif (!$aWhitelist || !(int)$aWhitelist['is_enabled']) {
            $aResult['reason'] = 'not_whitelisted';
        } elseif ($sIdentityType === 'self' && !(int)$aWhitelist['post_as_self']) {
            $aResult['reason'] = 'post_as_self_disabled';
        } elseif ($sIdentityType === 'page' && !(int)$aWhitelist['post_as_business']) {
            $aResult['reason'] = 'post_as_business_disabled';
        }

        if ($aResult['reason'] === null) {
            $aIdentity = db()->select('approved_identity_id')
                ->from(':hulahoot_swess_approved_identity')
                ->where([
                    'whitelist_id' => (int)$aWhitelist['whitelist_id'],
                    'identity_type' => $sIdentityType,
                    'identity_id' => $iIdentityId,
                    'is_active' => 1,
                ])
                ->execute('getSlaveRow');

            if (!$aIdentity) {
                $aResult['reason'] = 'identity_not_approved';
            } else {
                $aResult['allowed'] = true;
                $aResult['tags'] = $this->getTagsForIdentity((int)$aIdentity['approved_identity_id']);
            }
        }

        $this->logAudit($iUserId, $sIdentityType, $iIdentityId, $aResult['allowed'] ? 'post_check_allowed' : 'post_check_denied', [
            'reason' => $aResult['reason'],
        ]);

        return $aResult;
    }

    // ---- Audit log ---------------------------------------------------

    /**
     * @param int $iUserId
     * @param string|null $sIdentityType
     * @param int|null $iIdentityId
     * @param string $sAction
     * @param array $aContext
     * @param int|null $iActorUserId
     *
     * @return void
     */
    public function logAudit($iUserId, $sIdentityType, $iIdentityId, $sAction, array $aContext = [], $iActorUserId = null)
    {
        db()->insert(':hulahoot_swess_audit_log', [
            'user_id' => (int)$iUserId,
            'identity_type' => $sIdentityType,
            'identity_id' => $iIdentityId !== null ? (int)$iIdentityId : null,
            'action' => (string)$sAction,
            'context' => $aContext ? json_encode($aContext) : null,
            'actor_user_id' => $iActorUserId ?: null,
            'created' => time(),
        ]);
    }

    /**
     * @param int $iLimit
     *
     * @return array newest first, with the subject account's username attached
     */
    public function listAuditLog($iLimit = 100)
    {
        return (array)db()->select('a.*, u.user_name')
            ->from(':hulahoot_swess_audit_log', 'a')
            ->join(':user', 'u', 'u.user_id = a.user_id')
            ->order('a.id DESC')
            ->limit(0, (int)$iLimit)
            ->execute('getSlaveRows');
    }

    // ---- Internal ------------------------------------------------------

    /**
     * @param array $aData
     *
     * @return array
     *
     * @throws \InvalidArgumentException if name is blank
     */
    private function _validateTag(array $aData)
    {
        $sName = trim((string)($aData['name'] ?? ''));

        if ($sName === '') {
            throw new \InvalidArgumentException('Tag name is required.');
        }

        return [
            'name' => $sName,
            'description' => trim((string)($aData['description'] ?? '')) ?: null,
            'is_active' => !empty($aData['is_active']) ? 1 : 0,
            'ordering' => (int)($aData['ordering'] ?? 0),
        ];
    }
}
