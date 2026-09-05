<?php
/**
 * Fires at the end of \Photo::get() (PF.Site/Apps/core-photos/Service/
 * Photo.php), right before it returns [$iCnt, $aPhotos] - a one-line hook
 * call was added to that vendor file to reach this point (that file is
 * installer-extracted, not git-tracked - see docs/ProfileOwnership.md for
 * why, and re-add the hook call if a future installer/update run ever
 * overwrites it back to stock). $aPhotos is the final array of rows about
 * to be returned - this is the main photo-listing/browsing query (albums,
 * galleries), separate from how a photo-upload story renders inline in the
 * main feed (that path is covered by
 * hooks/feed.service_feed_get_before_parse_item.php instead).
 *
 * Same override as the feed/comment attribution hooks: replace
 * full_name/user_image/server_id (live-joined from phpfox_user) with the
 * values belonging to whichever profile actually uploaded the photo
 * (hulahoot_profile_id, stamped permanently at upload time by
 * hooks/photo.service_process_add__start.php). Rows with no
 * hulahoot_profile_id (pre-migration photos) are left untouched.
 *
 * IMPORTANT: this table also has an unrelated `profile_id` column on
 * phpfox_photo_album (pa.profile_id, aliased into $aPhoto['profile_id'] by
 * this same query) - deliberately not touched here, and not to be confused
 * with hulahoot_profile_id.
 *
 * DISABLED: the client decided to remove the Hulahoot multi-profile
 * switching experience entirely (Businesses/Organizations now use phpFox
 * Pages instead) - see docs/ProfileOwnership.md and the matching note in
 * hooks/feed.service_feed_get_before_parse_item.php (same regression, same
 * fix). Disabled via `false &&` rather than deleted - remove that to
 * reactivate. The one-line hook-call activation left in the vendor file
 * (Service/Photo.php) is unaffected by this and doesn't need reverting -
 * it now evaluates to a no-op since this file's logic never runs.
 */
if (false && !defined('PHPFOX_INSTALLER') && !empty($aPhotos) && is_array($aPhotos)) {
    $aProfileIds = [];
    foreach ($aPhotos as $aPhotoForIds) {
        if (!empty($aPhotoForIds['hulahoot_profile_id'])) {
            $aProfileIds[(int)$aPhotoForIds['hulahoot_profile_id']] = true;
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

                foreach ($aPhotos as $sOverrideKey => $aPhotoToOverride) {
                    if (empty($aPhotoToOverride['hulahoot_profile_id'])
                        || !isset($aProfilesById[(int)$aPhotoToOverride['hulahoot_profile_id']])
                    ) {
                        continue;
                    }

                    $aOwningProfile = $aProfilesById[(int)$aPhotoToOverride['hulahoot_profile_id']];

                    $aPhotos[$sOverrideKey]['full_name'] = $oIdentity->getDisplayName($aOwningProfile);
                    $aPhotos[$sOverrideKey]['user_image'] = $aOwningProfile['avatar_image'];
                    $aPhotos[$sOverrideKey]['server_id'] = $aOwningProfile['avatar_server_id'] !== null
                        ? $aOwningProfile['avatar_server_id']
                        : 0;
                }
            }
        } catch (\Exception $e) {
            Phpfox::getLog('hulahoot.log')->error(
                'Failed to apply per-profile attribution to photo rows: ' . $e->getMessage()
            );
        }
    }
}
