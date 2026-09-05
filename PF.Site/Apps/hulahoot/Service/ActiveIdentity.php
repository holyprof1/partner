<?php

namespace Apps\Hulahoot\Service;

/**
 * Class ActiveIdentity
 *
 * The single place in the codebase that answers "who is the active
 * profile, and what does the Master Account look like right now because
 * of it." Every controller, hook, or template that needs to know the
 * active Hulahoot profile - or needs to switch it - goes through here
 * instead of reading hulahoot_profile directly. See
 * docs/ActiveProfileIdentity.md for the full design rationale.
 *
 * What this class deliberately does NOT do: touch phpfox_user.user_name,
 * email, password, user_group_id, or any content/ownership table
 * (phpfox_feed, phpfox_photo, phpfox_comment, friend, notification, ...).
 * Those remain exactly as phpFox already manages them, keyed by the
 * Master Account's user_id. This class only ever writes the three
 * *display* fields a profile switch is supposed to change: full_name,
 * user_image (+server_id), and cover_photo (+cover_photo_top) - see
 * applyToMasterAccount() below.
 *
 * @package Apps\Hulahoot\Service
 */
class ActiveIdentity
{
    /**
     * The Master Account's currently-active profile (is_primary = 1),
     * or false if the account has no profile yet (shouldn't happen for
     * any account that completed registration, but callers should still
     * treat false as "nothing to resolve" rather than assume a row exists).
     *
     * @param int|null $iUserId defaults to the logged-in user
     *
     * @return array|false
     */
    public function getActiveProfile($iUserId = null)
    {
        $iUserId = $iUserId !== null ? (int)$iUserId : \Phpfox::getUserId();

        if ($iUserId <= 0) {
            return false;
        }

        return (new Profile())->getPrimaryByUserId($iUserId);
    }

    /**
     * What a profile's name should read as - its own label if the user
     * set one, otherwise its Profile Type's display name. Same fallback
     * Service\Profile::getByUserIdWithDetails() already uses for profile
     * cards, reused here so a synced full_name and a profile card never
     * disagree about what a nameless profile is called.
     *
     * @param array $aProfile a hulahoot_profile row
     *
     * @return string
     */
    public function getDisplayName(array $aProfile)
    {
        if (!empty($aProfile['profile_name'])) {
            return $aProfile['profile_name'];
        }

        $aType = (new Profile())->getProfileType((int)$aProfile['profile_type_id']);

        return $aType ? _p($aType['name']) : '';
    }

    /**
     * Snapshot the Master Account's current display fields (full_name is
     * deliberately excluded - see the note on applyToMasterAccount()) onto
     * one specific profile's own avatar/cover columns. Used two ways:
     * once as a one-time migration backfill (capturing what the account
     * already looked like before this feature existed, onto its existing
     * primary profile), and again every time switchTo() moves away from a
     * profile, so anything uploaded while it was active isn't lost the
     * next time it becomes primary.
     *
     * @param int $iUserId
     * @param int $iProfileId must belong to $iUserId
     *
     * @return void
     */
    public function captureMasterIdentityInto($iUserId, $iProfileId)
    {
        $iUserId = (int)$iUserId;
        $iProfileId = (int)$iProfileId;

        $aUser = db()->select('user_image, server_id')
            ->from(':user')
            ->where(['user_id' => $iUserId])
            ->execute('getSlaveRow');

        $aUserField = db()->select('cover_photo, cover_photo_top')
            ->from(':user_field')
            ->where(['user_id' => $iUserId])
            ->execute('getSlaveRow');

        if (!$aUser) {
            return;
        }

        db()->update(':hulahoot_profile', [
            'avatar_image' => $aUser['user_image'] !== '' ? $aUser['user_image'] : null,
            'avatar_server_id' => $aUser['server_id'],
            'cover_photo_id' => (!empty($aUserField['cover_photo']) ? (int)$aUserField['cover_photo'] : null),
            'cover_photo_top' => $aUserField ? $aUserField['cover_photo_top'] : null,
            'updated' => time(),
        ], ['profile_id' => $iProfileId, 'user_id' => $iUserId]);
    }

    /**
     * Write one profile's own display identity onto the shared
     * phpfox_user / phpfox_user_field row, so every existing phpFox
     * render path (header, profile page, feed/comment bylines - all of
     * which read those tables directly or via Phpfox::getUserBy(), never
     * through this class) shows it with zero changes to any of them.
     *
     * Deliberately limited to full_name, user_image/server_id, and
     * cover_photo/cover_photo_top - never user_name (the login credential
     * and profile URL slug), email, password, user_group_id, or anything
     * else. Content ownership (feed/photo/comment user_id, friend graph,
     * notifications) is untouched by this or any other method here.
     *
     * @param int $iUserId
     * @param int|null $iProfileId defaults to $iUserId's current active profile
     *
     * @return bool false if there's no active profile to apply
     */
    public function applyToMasterAccount($iUserId, $iProfileId = null)
    {
        $iUserId = (int)$iUserId;

        $aProfile = $iProfileId
            ? (new Profile())->getById((int)$iProfileId)
            : $this->getActiveProfile($iUserId);

        if (!$aProfile || (int)$aProfile['user_id'] !== $iUserId) {
            return false;
        }

        db()->update(':user', [
            'full_name' => $this->getDisplayName($aProfile),
            'user_image' => $aProfile['avatar_image'],
            'server_id' => $aProfile['avatar_server_id'] !== null ? $aProfile['avatar_server_id'] : 0,
        ], ['user_id' => $iUserId]);

        db()->update(':user_field', [
            'cover_photo' => $aProfile['cover_photo_id'] !== null ? $aProfile['cover_photo_id'] : 0,
            'cover_photo_top' => $aProfile['cover_photo_top'],
        ], ['user_id' => $iUserId]);

        return true;
    }

    /**
     * Switch which profile is active AND make the Master Account's
     * displayed identity match it - the combined operation every "switch
     * profile" control should call instead of Service\Profile::setPrimary()
     * directly. setPrimary() alone only flips is_primary; it has no
     * opinion about display fields, by design (see its own docblock) -
     * this is the one place those two steps are glued together, plus the
     * capture step that makes switching away from a profile lossless.
     *
     * @param int $iUserId
     * @param int $iNewProfileId must belong to $iUserId and be active
     *
     * @return bool
     *
     * @throws \InvalidArgumentException propagated from setPrimary() if
     *         $iNewProfileId doesn't belong to $iUserId or isn't active
     */
    public function switchTo($iUserId, $iNewProfileId)
    {
        $iUserId = (int)$iUserId;
        $iNewProfileId = (int)$iNewProfileId;

        $aOutgoing = $this->getActiveProfile($iUserId);

        if ($aOutgoing && (int)$aOutgoing['profile_id'] !== $iNewProfileId) {
            $this->captureMasterIdentityInto($iUserId, (int)$aOutgoing['profile_id']);
        }

        (new Profile())->setPrimary($iUserId, $iNewProfileId);

        return $this->applyToMasterAccount($iUserId, $iNewProfileId);
    }
}
