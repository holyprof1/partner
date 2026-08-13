<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class SwessWhitelistController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\Swess();

        $this->template()->setTitle(_p('hulahoot_admin_swess_whitelist'))
            ->setBreadCrumb(_p('hulahoot_admin_swess_whitelist'))
            ->assign([
                'entries' => $service->listWhitelist(),
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_swess_whitelist_clean')) ? eval($sPlugin) : false);
    }
}
