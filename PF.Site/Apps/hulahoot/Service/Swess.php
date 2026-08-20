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
    /**
     * Every distribution_target_type this architecture defines, per the
     * SWESS UI/UX spec §8 - the full set a post (and a whitelist row's
     * allowed_target_levels) may reference. Order matches the spec's own
     * level selector (City, State, Country, Continent, Site-wide).
     */
    const TARGET_LEVELS = ['city', 'state', 'country', 'continent', 'site_wide'];

    /**
     * The full SWESS post lifecycle, per the spec's status table (§10).
     * 'failed' is defined but nothing transitions a post into it yet -
     * there is no real external publish call in this phase that could
     * fail (see Service\Swess::submitPost()'s own docblock) - it exists
     * so the column and every status-aware view already handle it
     * correctly once real publishing is built.
     */
    const POST_STATUSES = ['draft', 'pending', 'approved', 'scheduled', 'published', 'failed', 'rejected', 'archived'];

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
     * @param array $aData is_enabled, post_as_self, post_as_business,
     *        requires_review, allowed_target_levels (array of
     *        'city'|'state'|'country'|'continent'|'site_wide', or empty/
     *        omitted for "every level allowed")
     * @param int|null $iActorUserId the admin making this change, for the
     *        audit log and enabled_by
     * @param int|null $iGrantedByPackageId internal use only - never passed
     *        by an AdminCP caller. Every AdminCP save goes through this
     *        method with this left at its default (null), which is exactly
     *        what makes an AdminCP save always win over - and permanently
     *        clear - any prior auto-grant: the moment an admin explicitly
     *        saves this screen for a user, granted_by_package_id resets to
     *        NULL and syncPackageEntitlement() will never touch that row
     *        again, regardless of what the user goes on to purchase. Only
     *        syncPackageEntitlement() itself passes a non-null value here.
     *
     * @return int the whitelist_id
     *
     * @throws \InvalidArgumentException if $iUserId doesn't resolve to a real account
     */
    public function setWhitelist($iUserId, array $aData, $iActorUserId = null, $iGrantedByPackageId = null)
    {
        $iUserId = (int)$iUserId;

        $aUser = db()->select('user_id')->from(':user')->where(['user_id' => $iUserId])->execute('getSlaveRow');
        if (!$aUser) {
            throw new \InvalidArgumentException('User ' . $iUserId . ' does not exist.');
        }

        $aLevels = array_values(array_intersect(
            (array)($aData['allowed_target_levels'] ?? []),
            self::TARGET_LEVELS
        ));

        $aClean = [
            'is_enabled' => !empty($aData['is_enabled']) ? 1 : 0,
            'post_as_self' => !empty($aData['post_as_self']) ? 1 : 0,
            'post_as_business' => !empty($aData['post_as_business']) ? 1 : 0,
            'requires_review' => !empty($aData['requires_review']) ? 1 : 0,
            // Empty selection = no restriction (every level allowed) -
            // matches this table's "NULL = unrestricted" convention, not
            // "nothing allowed".
            'allowed_target_levels' => $aLevels ? implode(',', $aLevels) : null,
            'granted_by_package_id' => $iGrantedByPackageId ? (int)$iGrantedByPackageId : null,
        ];

        $aExisting = $this->getWhitelistForUser($iUserId);
        $iNow = time();
        $bWasEnabled = $aExisting ? (bool)$aExisting['is_enabled'] : false;

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
            'granted_by_package_id' => $aClean['granted_by_package_id'],
        ], $iActorUserId);

        // Only notify on an actual enabled/disabled transition, not on
        // every unrelated field edit (e.g. an admin tweaking Require
        // Review shouldn't re-notify "SWESS enabled" every time).
        if ($bWasEnabled !== (bool)$aClean['is_enabled']) {
            notify('Hulahoot', $aClean['is_enabled'] ? 'whitelist_enabled' : 'whitelist_disabled', $iWhitelistId, $iUserId);
        }

        return $iWhitelistId;
    }

    /**
     * Auto-grant/reconcile SWESS access from the user's current active
     * subscription entitlement, per the SWESS spec's "qualifying package
     * purchase automatically grants SWESS entitlement" requirement.
     * Deliberately a lazy, idempotent "pull" (call this and it reconciles
     * to whatever's currently true) rather than a one-shot "push" fired
     * only at the instant a purchase completes - see this method's own
     * call sites for why: Service\PurchaseFlow::completeAsHulahoot() calls
     * it immediately after a free/admin-preview purchase completes (the
     * only completion path Hulahoot's own code controls end to end), and
     * every /hulahoot/swess/* route also calls it on every page load as a
     * safety net - because a real paid purchase completes entirely inside
     * native Core_Subscriptions code (RegisterController -> gateway driver
     * -> Callback::paymentApiCallback() -> Purchase\Process::update()),
     * which this app deliberately never hooks into or modifies (no safe
     * plugin hook exists before its side effects run - see
     * docs/PHASE_3_PAYMENT_GATEWAY.md). Calling this here instead means a
     * real gateway purchase still ends up with correct SWESS access the
     * next time the buyer loads any SWESS page, with zero native-app
     * changes.
     *
     * Never touches an admin-managed row (granted_by_package_id IS NULL) -
     * an admin's own configuration always wins, per the spec's explicit
     * "Admin must still retain control" requirement, and per the current
     * business rule confirmed directly: manually Admin-granted SWESS does
     * NOT expire alongside any subscription. Only ever:
     * - creates a new auto-granted row when the user has none yet,
     * - re-enables a row this method itself created earlier
     *   (granted_by_package_id IS NOT NULL) once a qualifying purchase
     *   exists again, or
     * - auto-revokes (disables, never deletes) a row it created earlier
     *   once NO currently-active qualifying purchase remains - see
     *   Service\Entitlement::getActiveSwessEntitlement()'s own docblock
     *   for exactly what "currently-active" means (any held purchase,
     *   not just the most recent), and Marketplace::
     *   reconcilePurchaseTermsForUser() (called first, below) for why a
     *   real gateway-paid purchase's expiry_date is trustworthy by the
     *   time this check runs. This auto-revoke behavior is the
     *   confirmed, current business rule - a package-granted entitlement
     *   that genuinely expires must stop being considered active.
     *
     * @param int $iUserId
     *
     * @return void
     */
    public function syncPackageEntitlement($iUserId)
    {
        $iUserId = (int)$iUserId;

        // Every entry point that calls this method already relies on it
        // to reconcile SWESS state to current truth - folding the
        // purchase-term fix in here too (rather than adding it
        // separately at every one of this method's own call sites) means
        // every existing caller gets a correctly-expired view of this
        // user's purchases for free. See that method's own docblock for
        // why this can only be a lazy correction, not a synchronous one.
        (new Marketplace())->reconcilePurchaseTermsForUser($iUserId);

        $aExisting = $this->getWhitelistForUser($iUserId);

        if ($aExisting && $aExisting['granted_by_package_id'] === null) {
            // Admin-managed row - never touched by auto-grant OR
            // auto-revoke, even if it's currently disabled/enabled. See
            // method docblock.
            return;
        }

        $aSwessEntitlement = (new Entitlement())->getActiveSwessEntitlement($iUserId);

        if ($aSwessEntitlement) {
            if ($aExisting && (int)$aExisting['is_enabled'] === 1) {
                // Already enabled by a prior auto-grant (possibly from a
                // different qualifying package) - nothing to do.
                return;
            }

            $this->setWhitelist($iUserId, [
                'is_enabled' => 1,
                'post_as_self' => 1,
                'post_as_business' => $aExisting['post_as_business'] ?? 0,
                'requires_review' => $aExisting['requires_review'] ?? 0,
                'allowed_target_levels' => $aExisting && $aExisting['allowed_target_levels']
                    ? explode(',', $aExisting['allowed_target_levels'])
                    : [],
            ], null, (int)$aSwessEntitlement['package_id']);

            return;
        }

        // No currently-active qualifying purchase. Only something to do
        // if a PRIOR auto-grant is still sitting enabled - a row that's
        // already disabled, or that never existed, needs nothing.
        if ($aExisting && (int)$aExisting['is_enabled'] === 1) {
            $this->setWhitelist($iUserId, [
                'is_enabled' => 0,
                'post_as_self' => $aExisting['post_as_self'],
                'post_as_business' => $aExisting['post_as_business'],
                'requires_review' => $aExisting['requires_review'],
                'allowed_target_levels' => $aExisting['allowed_target_levels']
                    ? explode(',', $aExisting['allowed_target_levels'])
                    : [],
                // Preserves the existing granted_by_package_id (passed
                // back in below, not left null) - keeps this row tagged
                // as auto-managed, so a later renewal correctly
                // re-enables it via the branch above instead of being
                // silently ignored as "admin-managed".
            ], null, (int)$aExisting['granted_by_package_id']);
        }
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
     * @param int $iApprovedIdentityId
     *
     * @return array|false the raw hulahoot_swess_approved_identity row
     *         (no tags attached - see getApprovedIdentities() for that),
     *         or false if it doesn't exist. Exists so callers - notably
     *         Controller\Admin\SwessWhitelistAddController's revoke_identity/
     *         assign_tag/unassign_tag actions - can confirm which
     *         whitelist_id an approved_identity_id actually belongs to
     *         before acting on it, rather than trusting a request param
     *         that a tampered form could point at a different whitelist
     *         entry entirely.
     */
    public function getApprovedIdentityById($iApprovedIdentityId)
    {
        return db()->select('*')
            ->from(':hulahoot_swess_approved_identity')
            ->where(['approved_identity_id' => (int)$iApprovedIdentityId])
            ->execute('getSlaveRow');
    }

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
     * @throws \InvalidArgumentException if identity_type is invalid, a 'self'
     *         identity is given that doesn't belong to $iUserId, or a 'page'
     *         identity is given that doesn't exist or isn't managed by $iUserId
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
        } else {
            // 'page' - reuses native Pages exactly as documented at the
            // top of this file: never verify against phpfox_pages/
            // phpfox_pages_admin directly, always go through the same
            // isAdmin() check the Pages app itself uses to decide who may
            // manage a Page. Without this, any integer was silently
            // accepted as a Page id regardless of who actually owns/
            // administers it - closes that gap.
            //
            // Core_Pages is CURRENTLY INACTIVE on this install (confirmed
            // live: Phpfox::isAppActive('Core_Pages') === false) - calling
            // Phpfox::getService('pages') against an inactive app doesn't
            // return null/false, it hard-errors ("Calling a Service from
            // an invalid Module"), which without this guard surfaced as an
            // uncaught 500 the moment anyone tried to approve a 'page'
            // identity - discovered by testing this exact path through a
            // real AdminCP request. This checks first and fails the same
            // clean, catchable way every other validation failure in this
            // method already does, with a message that says WHY rather
            // than a blank server error. Business/Page-identity SWESS
            // publishing is not usable at all until Core_Pages is
            // reactivated - that's an existing, pre-SWESS platform state,
            // not something this fix changes.
            if (!\Phpfox::isAppActive('Core_Pages')) {
                throw new \InvalidArgumentException('Business Page identities are unavailable: the native Pages app is not active on this install.');
            }

            $aPage = \Phpfox::getService('pages')->getPage($iIdentityId);

            if (!$aPage || !\Phpfox::getService('pages')->isAdmin($aPage, $iUserId)) {
                throw new \InvalidArgumentException('Page ' . $iIdentityId . ' does not exist or is not managed by user ' . $iUserId . '.');
            }
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
        notify('Hulahoot', 'identity_approved', $iApprovedId, $iUserId);

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
        notify('Hulahoot', 'identity_revoked', (int)$iApprovedIdentityId, (int)$aIdentity['user_id']);
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

    // ---- Posts (composer lifecycle) -------------------------------------

    /**
     * Which distribution target levels $aWhitelist allows, per its
     * allowed_target_levels column - every level when unset (that
     * column's own "NULL = unrestricted" convention).
     *
     * @param array $aWhitelist a hulahoot_swess_whitelist row
     *
     * @return array
     */
    public function getAllowedTargetLevels(array $aWhitelist)
    {
        if (empty($aWhitelist['allowed_target_levels'])) {
            return self::TARGET_LEVELS;
        }

        return array_values(array_intersect(
            explode(',', $aWhitelist['allowed_target_levels']),
            self::TARGET_LEVELS
        ));
    }

    /**
     * Create a new draft. Deliberately no validation beyond basic type-
     * cleaning - "no validation enforced" for Save as Draft, per spec.
     * The real checks (identity approved, tag assigned, target level
     * allowed, content present) happen in submitPost().
     *
     * @param int $iUserId
     * @param array $aData identity_type, identity_id, content, tag_id,
     *        distribution_target_type, distribution_target_value,
     *        distribution_target_label, scheduled_at
     *
     * @return int the new swess_post_id
     */
    public function createDraftPost($iUserId, array $aData)
    {
        $iNow = time();

        return (int)db()->insert(':hulahoot_swess_post', [
            'user_id' => (int)$iUserId,
            'identity_type' => (string)($aData['identity_type'] ?? ''),
            'identity_id' => (int)($aData['identity_id'] ?? 0),
            'content' => isset($aData['content']) ? (string)$aData['content'] : null,
            'tag_id' => !empty($aData['tag_id']) ? (int)$aData['tag_id'] : null,
            'distribution_target_type' => (string)($aData['distribution_target_type'] ?? 'site_wide'),
            'distribution_target_value' => $aData['distribution_target_value'] ?? null,
            'distribution_target_label' => $aData['distribution_target_label'] ?? null,
            'scheduled_at' => !empty($aData['scheduled_at']) ? (int)$aData['scheduled_at'] : null,
            'status' => 'draft',
            'created' => $iNow,
            'updated' => $iNow,
        ]);
    }

    /**
     * @param int $iPostId
     *
     * @return array|false
     */
    public function getPostById($iPostId)
    {
        return db()->select('*')->from(':hulahoot_swess_post')->where(['swess_post_id' => (int)$iPostId])->execute('getSlaveRow');
    }

    /**
     * Update a post - only while it's still editable per the spec's
     * "who can act" table: draft (freely), or rejected/failed (edit &
     * resubmit). Editing always resets status back to 'draft' - an
     * edited post is not automatically resubmitted, matching "Edit"
     * and "Submit" being two distinct actions everywhere else in the
     * composer.
     *
     * @param int $iPostId
     * @param int $iUserId must own the post
     * @param array $aData same shape as createDraftPost()
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the post doesn't belong to $iUserId or
     *         isn't currently editable
     */
    public function updatePost($iPostId, $iUserId, array $aData)
    {
        $aPost = $this->getPostById($iPostId);

        if (!$aPost || (int)$aPost['user_id'] !== (int)$iUserId) {
            throw new \InvalidArgumentException('Post ' . $iPostId . ' does not belong to user ' . $iUserId . '.');
        }

        if (!in_array($aPost['status'], ['draft', 'rejected', 'failed'], true)) {
            throw new \InvalidArgumentException('This post can no longer be edited (status: ' . $aPost['status'] . ').');
        }

        db()->update(':hulahoot_swess_post', [
            'identity_type' => (string)($aData['identity_type'] ?? $aPost['identity_type']),
            'identity_id' => (int)($aData['identity_id'] ?? $aPost['identity_id']),
            'content' => isset($aData['content']) ? (string)$aData['content'] : $aPost['content'],
            'tag_id' => !empty($aData['tag_id']) ? (int)$aData['tag_id'] : $aPost['tag_id'],
            'distribution_target_type' => (string)($aData['distribution_target_type'] ?? $aPost['distribution_target_type']),
            'distribution_target_value' => array_key_exists('distribution_target_value', $aData) ? $aData['distribution_target_value'] : $aPost['distribution_target_value'],
            'distribution_target_label' => array_key_exists('distribution_target_label', $aData) ? $aData['distribution_target_label'] : $aPost['distribution_target_label'],
            'scheduled_at' => !empty($aData['scheduled_at']) ? (int)$aData['scheduled_at'] : $aPost['scheduled_at'],
            'status' => 'draft',
            'rejection_reason' => null,
            'updated' => time(),
        ], ['swess_post_id' => (int)$iPostId]);

        return true;
    }

    /**
     * Submit a draft (or resubmit a rejected/failed one) - validates
     * everything the composer's Review step promises has already been
     * checked, then transitions to whichever status is actually correct
     * for this combination of admin-configured rules:
     *
     * requires_review?  scheduled?   -> status
     * yes                either      -> pending (approve/reject decides the rest)
     * no                 yes         -> scheduled
     * no                 no          -> published
     *
     * "published" here means SWESS's own bookkeeping considers the post
     * live - it does not create a phpfox_feed row or touch the native
     * Feed. Actually distributing a published SWESS post onto
     * hulahoot.com is the explicitly-deferred next phase (see the
     * hulahoot_swess_post table's own docblock) - nothing about that
     * boundary changes here.
     *
     * @param int $iPostId
     * @param int $iUserId must own the post
     *
     * @return array the updated post row
     *
     * @throws \InvalidArgumentException on any validation failure - identity no
     *         longer approved/enabled, tag not assigned to that identity, target
     *         level not allowed for this user, or content missing, or the post
     *         was already submitted by a concurrent request
     */
    public function submitPost($iPostId, $iUserId)
    {
        $iUserId = (int)$iUserId;

        // Named lock scoped to this one post - closes the double-click/
        // double-submit race: two near-simultaneous requests could both
        // read status='draft' before either write commits and both
        // proceed, publishing the same post twice over (and, worse, both
        // consuming a review slot). Same GET_LOCK/RELEASE_LOCK pattern
        // Service\PurchaseFlow::initiate() already uses per-package - see
        // that method's own docblock for why a short wait here is fine.
        $sLockName = 'hulahoot_swess_post_' . (int)$iPostId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 5) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            throw new \InvalidArgumentException('This post is being submitted already. Please try again.');
        }

        try {
            $aPost = $this->getPostById($iPostId);

            if (!$aPost || (int)$aPost['user_id'] !== $iUserId) {
                throw new \InvalidArgumentException('Post ' . $iPostId . ' does not belong to user ' . $iUserId . '.');
            }

            if (!in_array($aPost['status'], ['draft', 'rejected', 'failed'], true)) {
                throw new \InvalidArgumentException('This post has already been submitted.');
            }

            if (trim((string)$aPost['content']) === '') {
                throw new \InvalidArgumentException('This post has no content.');
            }

            $aCheck = $this->canPostAs($iUserId, $aPost['identity_type'], (int)$aPost['identity_id']);
            if (!$aCheck['allowed']) {
                throw new \InvalidArgumentException('This identity is no longer approved to post (' . $aCheck['reason'] . ').');
            }

            $aAssignedTagIds = array_map('intval', array_column($aCheck['tags'], 'tag_id'));
            if (empty($aPost['tag_id']) || !in_array((int)$aPost['tag_id'], $aAssignedTagIds, true)) {
                throw new \InvalidArgumentException('This identity has no matching disclosure tag assigned. Contact an Administrator.');
            }

            $aWhitelist = $this->getWhitelistForUser($iUserId);
            $aAllowedLevels = $this->getAllowedTargetLevels($aWhitelist);
            if (!in_array($aPost['distribution_target_type'], $aAllowedLevels, true)) {
                throw new \InvalidArgumentException('This target level is not allowed for your account.');
            }

            if ((bool)$aWhitelist['requires_review']) {
                $sNewStatus = 'pending';
            } elseif (!empty($aPost['scheduled_at'])) {
                $sNewStatus = 'scheduled';
            } else {
                $sNewStatus = 'published';
            }

            db()->update(':hulahoot_swess_post', [
                'status' => $sNewStatus,
                'rejection_reason' => null,
                'updated' => time(),
            ], ['swess_post_id' => (int)$iPostId]);

            $this->logAudit($iUserId, $aPost['identity_type'], (int)$aPost['identity_id'], 'post_submitted', [
                'swess_post_id' => (int)$iPostId,
                'new_status' => $sNewStatus,
            ]);
            notify('Hulahoot', 'post_submitted', (int)$iPostId, $iUserId);

            return $this->getPostById($iPostId);
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * @param int $iPostId
     * @param int $iActorUserId the approving admin
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the post isn't pending, the actor is
     *         the post's own author, or a concurrent request already acted on it
     */
    public function approvePost($iPostId, $iActorUserId)
    {
        $sLockName = 'hulahoot_swess_post_' . (int)$iPostId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 5) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            throw new \InvalidArgumentException('This post is being reviewed already. Please try again.');
        }

        try {
            $aPost = $this->getPostById($iPostId);

            if (!$aPost || $aPost['status'] !== 'pending') {
                throw new \InvalidArgumentException('Only a pending post can be approved.');
            }

            // No existing business rule permits self-approval - an admin
            // who happens to also be a SWESS publisher must have someone
            // else review their own submissions.
            if ((int)$aPost['user_id'] === (int)$iActorUserId) {
                throw new \InvalidArgumentException('You cannot approve your own post.');
            }

            $sNewStatus = !empty($aPost['scheduled_at']) ? 'scheduled' : 'published';

            db()->update(':hulahoot_swess_post', [
                'status' => $sNewStatus,
                'updated' => time(),
            ], ['swess_post_id' => (int)$iPostId]);

            $this->logAudit((int)$aPost['user_id'], $aPost['identity_type'], (int)$aPost['identity_id'], 'post_approved', [
                'swess_post_id' => (int)$iPostId,
                'new_status' => $sNewStatus,
            ], $iActorUserId);
            notify('Hulahoot', 'post_approved', (int)$iPostId, (int)$aPost['user_id']);
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * @param int $iPostId
     * @param string $sReason shown back to the publisher on their post detail view
     * @param int $iActorUserId the rejecting admin
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the post isn't pending, no reason was
     *         given, the actor is the post's own author, or a concurrent request
     *         already acted on it
     */
    public function rejectPost($iPostId, $sReason, $iActorUserId)
    {
        if (trim((string)$sReason) === '') {
            throw new \InvalidArgumentException('A rejection reason is required.');
        }

        $sLockName = 'hulahoot_swess_post_' . (int)$iPostId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 5) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            throw new \InvalidArgumentException('This post is being reviewed already. Please try again.');
        }

        try {
            $aPost = $this->getPostById($iPostId);

            if (!$aPost || $aPost['status'] !== 'pending') {
                throw new \InvalidArgumentException('Only a pending post can be rejected.');
            }

            if ((int)$aPost['user_id'] === (int)$iActorUserId) {
                throw new \InvalidArgumentException('You cannot reject your own post.');
            }

            db()->update(':hulahoot_swess_post', [
                'status' => 'rejected',
                'rejection_reason' => trim((string)$sReason),
                'updated' => time(),
            ], ['swess_post_id' => (int)$iPostId]);

            $this->logAudit((int)$aPost['user_id'], $aPost['identity_type'], (int)$aPost['identity_id'], 'post_rejected', [
                'swess_post_id' => (int)$iPostId,
                'reason' => $sReason,
            ], $iActorUserId);
            notify('Hulahoot', 'post_rejected', (int)$iPostId, (int)$aPost['user_id']);
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * Cancel a still-pending/scheduled post - moves it to 'archived',
     * never deletes it, per spec ("preserving the audit trail").
     *
     * @param int $iPostId
     * @param int $iUserId must own the post
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the post doesn't belong to $iUserId,
     *         isn't in a cancellable status, or a concurrent request already
     *         acted on it (e.g. an admin approving/rejecting at the same instant)
     */
    public function cancelPost($iPostId, $iUserId)
    {
        $sLockName = 'hulahoot_swess_post_' . (int)$iPostId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 5) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            throw new \InvalidArgumentException('This post is being updated already. Please try again.');
        }

        try {
            $aPost = $this->getPostById($iPostId);

            if (!$aPost || (int)$aPost['user_id'] !== (int)$iUserId) {
                throw new \InvalidArgumentException('Post ' . $iPostId . ' does not belong to user ' . $iUserId . '.');
            }

            if (!in_array($aPost['status'], ['pending', 'approved', 'scheduled'], true)) {
                throw new \InvalidArgumentException('This post can no longer be cancelled.');
            }

            db()->update(':hulahoot_swess_post', [
                'status' => 'archived',
                'updated' => time(),
            ], ['swess_post_id' => (int)$iPostId]);

            $this->logAudit((int)$iUserId, $aPost['identity_type'], (int)$aPost['identity_id'], 'post_cancelled', [
                'swess_post_id' => (int)$iPostId,
            ]);
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * Delete a draft outright - the one status the spec allows a hard
     * delete for rather than archiving.
     *
     * @param int $iPostId
     * @param int $iUserId must own the post
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the post doesn't belong to $iUserId or
     *         isn't a draft
     */
    public function deleteDraftPost($iPostId, $iUserId)
    {
        $aPost = $this->getPostById($iPostId);

        if (!$aPost || (int)$aPost['user_id'] !== (int)$iUserId) {
            throw new \InvalidArgumentException('Post ' . $iPostId . ' does not belong to user ' . $iUserId . '.');
        }

        if ($aPost['status'] !== 'draft') {
            throw new \InvalidArgumentException('Only a draft can be deleted directly - use Cancel instead.');
        }

        db()->delete(':hulahoot_swess_post', ['swess_post_id' => (int)$iPostId]);
    }

    /**
     * @param int $iUserId
     * @param array $aFilters optional: status, identity_type, tag_id
     *
     * @return array newest-updated first
     */
    public function getPostsForUser($iUserId, array $aFilters = [])
    {
        $aWhere = ['user_id' => (int)$iUserId];

        if (!empty($aFilters['status'])) {
            $aWhere['status'] = (string)$aFilters['status'];
        }
        if (!empty($aFilters['identity_type'])) {
            $aWhere['identity_type'] = (string)$aFilters['identity_type'];
        }
        if (!empty($aFilters['tag_id'])) {
            $aWhere['tag_id'] = (int)$aFilters['tag_id'];
        }

        return (array)db()->select('*')
            ->from(':hulahoot_swess_post')
            ->where($aWhere)
            ->order('updated DESC')
            ->execute('getSlaveRows');
    }

    /**
     * The AdminCP Approval Queue - every post currently 'pending',
     * across every publisher, oldest first (first submitted, first
     * reviewed).
     *
     * @return array
     */
    public function getPendingPosts()
    {
        return (array)db()->select('p.*, u.user_name')
            ->from(':hulahoot_swess_post', 'p')
            ->join(':user', 'u', 'u.user_id = p.user_id')
            ->where(['p.status' => 'pending'])
            ->order('p.created ASC')
            ->execute('getSlaveRows');
    }

    /**
     * Publish every 'scheduled' post whose scheduled_at has arrived -
     * called from publish-scheduled-swess-posts.php, a standalone CLI
     * script (its own crontab entry, not phpFox's native phpfox_cron
     * table) since nothing on this domain currently triggers native cron
     * at all - see docs/PHASE_3_PAYMENT_GATEWAY.md and that script's own
     * docblock for why, matching the exact precedent already established
     * by send-expiry-reminders.php.
     *
     * "Publish" here means the same thing it means everywhere else in
     * this phase (see submitPost()'s docblock): SWESS's own bookkeeping
     * only, no phpfox_feed row, no call to hulahoot.com. A post that fails
     * this transition (should never happen today - the transition itself
     * is just a status flip - but the branch exists so a real future
     * failure has somewhere correct to go) is marked 'failed' with a
     * rejection_reason explaining why, rather than left silently stuck as
     * 'scheduled' forever.
     *
     * @return array{published: int[], failed: int[]} the post ids actually
     *         transitioned, for the cron script's own log line
     */
    public function publishDuePosts()
    {
        // A single raw WHERE string, not two chained ->where() calls -
        // this query builder's where() replaces its clause on every call
        // rather than ANDing them together (confirmed by reading
        // Phpfox_Database_Driver_Mysql::where() directly), so a second
        // ->where() call would silently drop the status filter entirely.
        $aDue = (array)db()->select('*')
            ->from(':hulahoot_swess_post')
            ->where("status = 'scheduled' AND scheduled_at <= " . time())
            ->execute('getSlaveRows');

        $aPublished = [];
        $aFailed = [];

        foreach ($aDue as $aPost) {
            $iPostId = (int)$aPost['swess_post_id'];

            // Same per-post lock every other lifecycle transition uses -
            // a post being cancelled by its owner at the exact instant
            // this cron runs must not also get published.
            $sLockName = 'hulahoot_swess_post_' . $iPostId;
            $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 5) AS locked")->from('DUAL')->execute('getSlaveRow');

            if (empty($aLockResult['locked'])) {
                continue;
            }

            try {
                // Re-check status under the lock - the row could have
                // been cancelled between the SELECT above and acquiring
                // the lock.
                $aFresh = $this->getPostById($iPostId);
                if (!$aFresh || $aFresh['status'] !== 'scheduled') {
                    continue;
                }

                try {
                    db()->update(':hulahoot_swess_post', [
                        'status' => 'published',
                        'updated' => time(),
                    ], ['swess_post_id' => $iPostId]);

                    $this->logAudit((int)$aFresh['user_id'], $aFresh['identity_type'], (int)$aFresh['identity_id'], 'post_published', [
                        'swess_post_id' => $iPostId,
                    ]);
                    notify('Hulahoot', 'post_published', $iPostId, (int)$aFresh['user_id']);
                    $aPublished[] = $iPostId;
                } catch (\Throwable $e) {
                    db()->update(':hulahoot_swess_post', [
                        'status' => 'failed',
                        'rejection_reason' => substr($e->getMessage(), 0, 255),
                        'updated' => time(),
                    ], ['swess_post_id' => $iPostId]);

                    $this->logAudit((int)$aFresh['user_id'], $aFresh['identity_type'], (int)$aFresh['identity_id'], 'post_failed', [
                        'swess_post_id' => $iPostId,
                        'reason' => $e->getMessage(),
                    ]);
                    notify('Hulahoot', 'post_failed', $iPostId, (int)$aFresh['user_id']);
                    $aFailed[] = $iPostId;
                }
            } finally {
                db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
            }
        }

        return ['published' => $aPublished, 'failed' => $aFailed];
    }

    // ---- Main Hulahoot hand-off ------------------------------------------

    /**
     * The clean internal read boundary for the eventual main-Hulahoot
     * publishing pipeline: everything it will need to actually publish
     * one SWESS post, assembled in a single call rather than requiring
     * that future integration to know which several tables/services to
     * query itself (post row, resolved identity, resolved tag, target,
     * scheduling, and the entitlement snapshot that justified it).
     *
     * Deliberately read-only, side-effect-free, and internal-only - this
     * is the SHAPE of the hand-off contract, not a real API and not the
     * publishing pipeline itself, which belongs to main Hulahoot and is
     * explicitly out of scope here (see docs on why this app never fakes
     * a Hulahoot API or calls hulahoot.com directly).
     *
     * Only ever returns a payload for a post actually in a publish-
     * eligible status ('published' or 'scheduled' - scheduled carries
     * its own scheduled_at for the pipeline to respect) - every other
     * status (draft/pending/rejected/failed/cancelled/archived) returns
     * null, since none of those represent something ready to hand off.
     *
     * @param int $iPostId
     *
     * @return array|null null if the post doesn't exist or isn't
     *         publish-eligible, else:
     *         {
     *             post_id, user_id, status, content, created, updated,
     *             identity: {type: 'self'|'page', id, profile?: hulahoot_profile
     *                 row (identity_type='self'), page?: native Page row or
     *                 null (identity_type='page', only resolved while
     *                 Core_Pages is active)},
     *             tag: {tag_id, name, description}|null,
     *             target: {type, value, label} (distribution_target_*,
     *                 unchanged - value is null today, see
     *                 distribution_target_value's own docblock on why:
     *                 the composer doesn't populate it yet, pending the
     *                 main Hulahoot Location & Context Service),
     *             scheduled_at: int|null,
     *             entitlement: Service\Entitlement::getActiveEntitlement()'s
     *                 snapshot for this post's user at hand-off time
     *         }
     */
    public function getPublishPayload($iPostId)
    {
        $aPost = $this->getPostById($iPostId);

        if (!$aPost || !in_array($aPost['status'], ['published', 'scheduled'], true)) {
            return null;
        }

        $aIdentity = [
            'type' => $aPost['identity_type'],
            'id' => (int)$aPost['identity_id'],
        ];

        if ($aPost['identity_type'] === 'self') {
            $aIdentity['profile'] = (new Profile())->getById((int)$aPost['identity_id']) ?: null;
        } elseif ($aPost['identity_type'] === 'page' && \Phpfox::isAppActive('Core_Pages')) {
            $aIdentity['page'] = \Phpfox::getService('pages')->getPage((int)$aPost['identity_id']) ?: null;
        }

        $aTag = $aPost['tag_id'] ? $this->getTagById((int)$aPost['tag_id']) : null;

        return [
            'post_id' => (int)$aPost['swess_post_id'],
            'user_id' => (int)$aPost['user_id'],
            'status' => $aPost['status'],
            'content' => $aPost['content'],
            'identity' => $aIdentity,
            'tag' => $aTag ? [
                'tag_id' => (int)$aTag['tag_id'],
                'name' => $aTag['name'],
                'description' => $aTag['description'],
            ] : null,
            'target' => [
                'type' => $aPost['distribution_target_type'],
                'value' => $aPost['distribution_target_value'],
                'label' => $aPost['distribution_target_label'],
            ],
            'scheduled_at' => $aPost['scheduled_at'] !== null ? (int)$aPost['scheduled_at'] : null,
            'created' => (int)$aPost['created'],
            'updated' => (int)$aPost['updated'],
            'entitlement' => (new Entitlement())->getActiveEntitlement((int)$aPost['user_id']),
        ];
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
