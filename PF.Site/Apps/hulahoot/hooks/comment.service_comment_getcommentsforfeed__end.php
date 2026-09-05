<?php
/**
 * Fires at the end of \Comment::getCommentsForFeed() (PF.Site/Apps/
 * core-comments/Service/Comment.php), right before it returns $aComments -
 * a one-line hook call was added to that vendor file to reach this point
 * (that file is installer-extracted, not git-tracked - see
 * docs/ProfileOwnership.md for why, and re-add the hook call if a future
 * installer/update run ever overwrites it back to stock). $aComments is
 * the final array about to be returned to every caller (the feed comment
 * block, the comments AJAX endpoints, the REST API) - all of them share
 * this one method, so fixing it here covers all of them.
 *
 * Same override as hooks/feed.service_feed_get_before_parse_item.php:
 * replace full_name/user_image/server_id (live-joined from phpfox_user, so
 * currently reflecting whichever profile is active *now*) with the values
 * belonging to whichever profile actually posted the comment
 * (hulahoot_profile_id, stamped permanently at creation time by
 * hooks/comment.service_process_add.php). Rows with no
 * hulahoot_profile_id (pre-migration comments) are left untouched -
 * today's live-join behavior, not a regression.
 *
 * Only overrides top-level rows in $aComments - nested replies (if this
 * method's result includes them) are not walked by this pass. Flagged as a
 * known, smaller residual gap rather than blocking the main fix.
 *
 * DISABLED: the client decided to remove the Hulahoot multi-profile
 * switching experience entirely (Businesses/Organizations now use phpFox
 * Pages instead) - see docs/ProfileOwnership.md and the matching note in
 * hooks/feed.service_feed_get_before_parse_item.php (same regression, same
 * fix). Disabled via `false &&` rather than deleted - remove that to
 * reactivate. The one-line hook-call activation left in the vendor file
 * (Service/Comment.php) is unaffected by this and doesn't need reverting -
 * it now evaluates to a no-op since this file's logic never runs.
 */
if (false && !defined('PHPFOX_INSTALLER') && !empty($aComments) && is_array($aComments)) {
    $aProfileIds = [];
    foreach ($aComments as $aCommentForIds) {
        if (!empty($aCommentForIds['hulahoot_profile_id'])) {
            $aProfileIds[(int)$aCommentForIds['hulahoot_profile_id']] = true;
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

                foreach ($aComments as $sOverrideKey => $aCommentToOverride) {
                    if (empty($aCommentToOverride['hulahoot_profile_id'])
                        || !isset($aProfilesById[(int)$aCommentToOverride['hulahoot_profile_id']])
                    ) {
                        continue;
                    }

                    $aOwningProfile = $aProfilesById[(int)$aCommentToOverride['hulahoot_profile_id']];

                    $aComments[$sOverrideKey]['full_name'] = $oIdentity->getDisplayName($aOwningProfile);
                    $aComments[$sOverrideKey]['user_image'] = $aOwningProfile['avatar_image'];
                    $aComments[$sOverrideKey]['server_id'] = $aOwningProfile['avatar_server_id'] !== null
                        ? $aOwningProfile['avatar_server_id']
                        : 0;
                }
            }
        } catch (\Exception $e) {
            Phpfox::getLog('hulahoot.log')->error(
                'Failed to apply per-profile attribution to comment rows: ' . $e->getMessage()
            );
        }
    }
}
