<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

defined('PHPFOX') or exit('NO DICE!');

/**
 * Mass-callback service - phpFox auto-discovers this class (by convention,
 * once the app has an addAliasNames() entry in start.php) and invokes any
 * method here whose name matches a callback phpFox broadcasts to every
 * installed app. See Phpfox_Module::_loadCallbacks() /
 * Phpfox_Module::massCallback().
 */
class Callback
{
    /**
     * Contributes the "SWESS Wallet" tab to a user's own profile menu, the
     * same extension point every other first-party app (Photos, Pages,
     * Videos) already uses for their own profile tabs - see
     * PF.Base/module/profile/include/service/profile.class.php:53.
     *
     * Placeholder only: the tab exists and is reachable, but there is no
     * Wallet feature behind it yet - Controller\ProfileRedirectController
     * just sends the user back to their own profile. Only shown on the
     * viewer's own profile (mirrors core's own "Statistics" tab, which
     * uses the same is-this-my-profile check for the same reason: a
     * wallet is private, not something to show on someone else's page).
     *
     * @param array $aUser the profile currently being viewed
     *
     * @return array
     */
    public function getProfileMenu($aUser)
    {
        if ((int)$aUser['user_id'] !== (int)Phpfox::getUserId()) {
            return [];
        }

        return [
            [
                'phrase' => _p('hulahoot_swess_wallet'),
                'url' => 'profile.hulahoot',
                'actual_url' => 'profile_hulahoot',
            ],
        ];
    }
}
