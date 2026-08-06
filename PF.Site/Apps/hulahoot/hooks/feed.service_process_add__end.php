<?php
/**
 * Fires at the end of Feed_Service_Process::add() (PF.Base/module/feed/
 * include/service/process.class.php), right before $aInsert is written to
 * phpfox_feed - see the enclosing method for the exact line. $aInsert is
 * the literal array about to be passed to db()->insert(), already keyed by
 * every column add() sets, including 'user_id' (the resolved $post_user_id
 * - the Master Account this story is being posted as, already accounting
 * for any $iOwnerUserId override such as Pages-authored content). Adding a
 * key here is sufficient to have it written, since $aInsert is used
 * unmodified a few lines later.
 *
 * Stamps which Hulahoot profile was active for that user at the moment of
 * posting - this becomes the story's permanent owner. Every subsequent
 * profile switch, by this or any other account, must never revisit this
 * value - see docs/ProfileOwnership.md. This is the single choke point for
 * essentially all feed-story creation: status updates, photo-upload
 * stories, and cover-photo-change stories all route through this same
 * add() call (confirmed by reading User_Service_Process - see
 * process.class.php lines ~644, ~1633, ~1710, ~1821), so this one hook
 * covers all of them.
 *
 * Never overwrites an already-set hulahoot_profile_id (defensive, in case
 * a future caller pre-supplies one) and no-ops silently if the account has
 * no active profile - this must never be what breaks posting.
 *
 * DISABLED: the client decided to remove the Hulahoot multi-profile
 * switching experience entirely (Businesses/Organizations now use phpFox
 * Pages instead). With no way to ever create a second profile, every new
 * registration's one profile has no profile_name set, and the paired read
 * hook (feed.service_feed_get_before_parse_item.php) was falling back to
 * the Profile Type's generic name (e.g. "Personal") instead of the
 * account's real name for any content stamped here. Disabled via
 * `false &&` rather than deleted - remove that to reactivate. See
 * docs/ProfileOwnership.md.
 */
if (false && !defined('PHPFOX_INSTALLER') && isset($aInsert['user_id']) && (int)$aInsert['user_id'] > 0 && empty($aInsert['hulahoot_profile_id'])) {
    try {
        $aActiveProfile = (new \Apps\Hulahoot\Service\ActiveIdentity())->getActiveProfile((int)$aInsert['user_id']);

        if ($aActiveProfile) {
            $aInsert['hulahoot_profile_id'] = (int)$aActiveProfile['profile_id'];
        }
    } catch (\Exception $e) {
        Phpfox::getLog('hulahoot.log')->error(
            'Failed to stamp hulahoot_profile_id on a new feed row for user ' . $aInsert['user_id'] . ': ' . $e->getMessage()
        );
    }
}
