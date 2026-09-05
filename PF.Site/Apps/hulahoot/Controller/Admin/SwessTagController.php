<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class SwessTagController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template(), \Apps\Hulahoot\Service\AdmincpChrome::swessLinks());

        $service = new \Apps\Hulahoot\Service\Swess();

        $this->template()->setTitle(_p('hulahoot_admin_swess_tags'))
            ->setBreadCrumb(_p('hulahoot_admin_swess_tags'))
            ->assign([
                'tags' => $service->listTags(),
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_swess_tag_clean')) ? eval($sPlugin) : false);
    }
}
