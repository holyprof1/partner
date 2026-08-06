<?php
/**
 * Fires at the end of User_Service_Process::add() (PF.Base/module/user/
 * include/service/process.class.php), right before it returns the new
 * user_id - i.e. only once the account row genuinely exists. $iId is the
 * new user's phpfox_user.user_id, and $aVals is the raw submitted
 * registration array, both already set at this point in the enclosing
 * method's scope (hook code executes via eval() in that scope - see
 * docs/ProjectStructure.md §8). $aVals is unmodified by this point in
 * add() - confirmed by reading process.class.php directly.
 *
 * Creates the user's one initial profile using whichever individual-
 * classification type they picked in the registration form's account-type
 * dropdown (val[hulahoot_profile_type_id] - rendered by hooks/user.
 * template_default_block_register_step1_4.php, an official phpFox
 * extension point in the core template, not a core file edit). Falls back
 * to hulahoot_profile_type.is_default if the field is missing or doesn't
 * resolve to a currently-active individual type (tampered request, a type
 * an administrator deactivated between page load and submit, etc.) - a
 * user should never end up with zero profiles just because of that.
 *
 * Registration must never fail because of this - if profile creation
 * errors for any reason, it's logged and swallowed, not surfaced to the
 * registering user.
 */
if (!defined('PHPFOX_INSTALLER') && isset($iId) && $iId) {
    try {
        $oHulahootProfile = new \Apps\Hulahoot\Service\Profile();

        if (!$oHulahootProfile->userHasProfile($iId)) {
            $iSubmittedTypeId = isset($aVals['hulahoot_profile_type_id']) ? (int)$aVals['hulahoot_profile_type_id'] : 0;

            if ($iSubmittedTypeId && $oHulahootProfile->isActiveIndividualProfileType($iSubmittedTypeId)) {
                $iProfileTypeId = $iSubmittedTypeId;
            } else {
                $iProfileTypeId = $oHulahootProfile->getDefaultProfileTypeId();
            }

            if ($iProfileTypeId) {
                $oHulahootProfile->create($iId, $iProfileTypeId);
            } else {
                Phpfox::getLog('hulahoot.log')->error(
                    'Skipped auto-creating a profile for new user ' . $iId
                    . ': no default Profile Type is configured (hulahoot_profile_type.is_default).'
                );
            }
        }
    } catch (\Exception $e) {
        Phpfox::getLog('hulahoot.log')->error(
            'Failed to auto-create a profile for new user ' . $iId . ': ' . $e->getMessage()
        );
    }
}
