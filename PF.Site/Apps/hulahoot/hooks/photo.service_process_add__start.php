<?php
/**
 * Fires at the very start of \Process::add($iUserId, $aVals, $bIsUpdate,
 * $bAllowTitleUrl) (PF.Site/Apps/core-photos/Service/Process.php), before
 * $aFields (the whitelist of columns db()->process()->insert() will
 * actually write) is populated. $iUserId, $aVals, and $bIsUpdate are all
 * method parameters, already in scope.
 *
 * Stamps which Hulahoot profile was active for $iUserId at the moment of
 * upload, by adding a key to $aFields (the column whitelist) and a value
 * to $aVals (the same array the rest of add() progressively fills in) -
 * $aFields is never reset after this point in the method, so the addition
 * survives through to the actual INSERT. See docs/ProfileOwnership.md.
 *
 * Only for genuinely new photos ($bIsUpdate is empty/false) - add() is also
 * used to edit/move an existing photo's metadata, which must never change
 * who originally uploaded it.
 *
 * IMPORTANT naming note: phpfox_photo_album already has its own, unrelated
 * `profile_id` column (used to flag an album as a user's profile-picture
 * album - confirmed by reading Service/Photo.php). This uses
 * `hulahoot_profile_id` specifically to avoid colliding with that existing
 * column when both appear in the same SELECT's result array.
 *
 * Never overwrites an already-set value and no-ops silently if the account
 * has no active profile - this must never be what breaks an upload.
 *
 * DISABLED: the client decided to remove the Hulahoot multi-profile
 * switching experience entirely (Businesses/Organizations now use phpFox
 * Pages instead) - see docs/ProfileOwnership.md and the matching note in
 * hooks/feed.service_process_add__end.php. Disabled via `false &&` rather
 * than deleted - remove that to reactivate.
 */
if (false && !defined('PHPFOX_INSTALLER') && empty($bIsUpdate) && isset($iUserId) && (int)$iUserId > 0 && empty($aVals['hulahoot_profile_id'])) {
    try {
        $aActiveProfile = (new \Apps\Hulahoot\Service\ActiveIdentity())->getActiveProfile((int)$iUserId);

        if ($aActiveProfile) {
            $aFields['hulahoot_profile_id'] = 'int';
            $aVals['hulahoot_profile_id'] = (int)$aActiveProfile['profile_id'];
        }
    } catch (\Exception $e) {
        Phpfox::getLog('hulahoot.log')->error(
            'Failed to stamp hulahoot_profile_id on a new photo for user ' . $iUserId . ': ' . $e->getMessage()
        );
    }
}
