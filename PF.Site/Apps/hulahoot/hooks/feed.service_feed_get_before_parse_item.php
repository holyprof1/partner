<?php
/**
 * Fires inside Feed_Service_Feed::get() (PF.Base/module/feed/include/
 * service/feed.class.php), after $aRows has been fetched (every query
 * variant in get() selects `feed.*`, so hulahoot_profile_id - added by
 * this project's schema migration - is already present on every row with
 * zero changes to any of those queries) but before the foreach loop that
 * hands each row to _processFeed() / the per-type getActivityFeed
 * callbacks. $aRows is a plain array in the enclosing method's scope, so
 * mutating it here is visible to that loop, which runs after this hook.
 *
 * Overrides each row's full_name/user_image/server_id - the fields that
 * came from the live JOIN to phpfox_user - with the values belonging to
 * whichever Hulahoot profile actually created it (hulahoot_profile_id,
 * stamped permanently at creation time by
 * hooks/feed.service_process_add__end.php). This is what makes a story
 * display as its true, permanent author regardless of which profile the
 * account has switched to since. Deliberately does NOT touch user_name -
 * that's the login/profile-URL slug, unrelated to which Hulahoot profile
 * authored this story, and untouched by design (see
 * docs/ProfileOwnership.md - separate per-profile timeline URLs are a
 * later phase, not this one).
 *
 * Rows with no hulahoot_profile_id (legacy content from before this
 * migration, or any insertion path this pass didn't cover) are left
 * exactly as-is - they keep today's behavior: full_name/user_image as
 * live-joined from phpfox_user, i.e. whichever profile is currently
 * active. That NULL fallback is deliberate, not an oversight.
 *
 * Batches the profile lookup into one query instead of one per row.
 *
 * DISABLED: the client decided to remove the Hulahoot multi-profile
 * switching experience entirely (Businesses/Organizations now use phpFox
 * Pages instead). With every registration's one profile having no
 * profile_name set, getDisplayName() was falling back to the Profile
 * Type's generic name (e.g. "Personal") instead of the account's real
 * name for any content stamped by the paired write hook - a live
 * regression, verified directly. Disabling this restores phpFox's
 * original behavior: full_name/user_image live-joined from phpfox_user,
 * i.e. the account's actual current name, for every post regardless of
 * hulahoot_profile_id. Disabled via `false &&` rather than deleted -
 * remove that to reactivate. See docs/ProfileOwnership.md.
 */
if (false && !defined('PHPFOX_INSTALLER') && !empty($aRows) && is_array($aRows)) {
    $aProfileIds = [];
    foreach ($aRows as $aRowForIds) {
        if (!empty($aRowForIds['hulahoot_profile_id'])) {
            $aProfileIds[(int)$aRowForIds['hulahoot_profile_id']] = true;
        }
    }

    if ($aProfileIds) {
        try {
            $aProfiles = db()->select('*')
                ->from(':hulahoot_profile')
                ->where(['profile_id' => ['in' => implode(',', array_keys($aProfileIds))]])
                ->execute('getSlaveRows');

            $aProfilesById = [];
            foreach ($aProfiles as $aProfileRow) {
                $aProfilesById[(int)$aProfileRow['profile_id']] = $aProfileRow;
            }

            if ($aProfilesById) {
                $oIdentity = new \Apps\Hulahoot\Service\ActiveIdentity();

                foreach ($aRows as $sOverrideKey => $aRowToOverride) {
                    if (empty($aRowToOverride['hulahoot_profile_id'])
                        || !isset($aProfilesById[(int)$aRowToOverride['hulahoot_profile_id']])
                    ) {
                        continue;
                    }

                    $aOwningProfile = $aProfilesById[(int)$aRowToOverride['hulahoot_profile_id']];

                    $aRows[$sOverrideKey]['full_name'] = $oIdentity->getDisplayName($aOwningProfile);
                    $aRows[$sOverrideKey]['user_image'] = $aOwningProfile['avatar_image'];
                    $aRows[$sOverrideKey]['server_id'] = $aOwningProfile['avatar_server_id'] !== null
                        ? $aOwningProfile['avatar_server_id']
                        : 0;
                }
            }
        } catch (\Exception $e) {
            Phpfox::getLog('hulahoot.log')->error(
                'Failed to apply per-profile attribution to feed rows: ' . $e->getMessage()
            );
        }
    }
}
