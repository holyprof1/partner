<?php
/**
 * Fires inside \Process::add($aVals, $iUserId, $sUserName)
 * (PF.Site/Apps/core-comments/Service/Process.php), immediately before
 * $aInsert is written to phpfox_comment. $aInsert is the literal array
 * about to be passed to $this->database()->insert(), and $iUserId has
 * already been resolved to a concrete int at the top of the method (falls
 * back to Phpfox::getUserId() when the caller didn't pass one) - so it's
 * always safe to read here regardless of who called add().
 *
 * Stamps which Hulahoot profile was active for $iUserId at the moment of
 * commenting - this becomes the comment's permanent owner. See
 * docs/ProfileOwnership.md.
 *
 * Never overwrites an already-set hulahoot_profile_id and no-ops silently
 * if the account has no active profile - this must never be what breaks
 * posting a comment.
 *
 * DISABLED: the client decided to remove the Hulahoot multi-profile
 * switching experience entirely (Businesses/Organizations now use phpFox
 * Pages instead) - see docs/ProfileOwnership.md and the matching note in
 * hooks/feed.service_process_add__end.php. Disabled via `false &&` rather
 * than deleted - remove that to reactivate.
 */
if (false && !defined('PHPFOX_INSTALLER') && isset($iUserId) && (int)$iUserId > 0 && empty($aInsert['hulahoot_profile_id'])) {
    try {
        $aActiveProfile = (new \Apps\Hulahoot\Service\ActiveIdentity())->getActiveProfile((int)$iUserId);

        if ($aActiveProfile) {
            $aInsert['hulahoot_profile_id'] = (int)$aActiveProfile['profile_id'];
        }
    } catch (\Exception $e) {
        Phpfox::getLog('hulahoot.log')->error(
            'Failed to stamp hulahoot_profile_id on a new comment for user ' . $iUserId . ': ' . $e->getMessage()
        );
    }
}
