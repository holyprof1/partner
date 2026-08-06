<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class ProfileTypeController extends Phpfox_Component
{
    public function process()
    {
        $service = new \Apps\Hulahoot\Service\ProfileTypeAdmin();

        $this->template()->setTitle(_p('hulahoot_admin_profile_types'))
            ->setBreadCrumb(_p('hulahoot_admin_profile_types'))
            ->assign([
                'types' => $service->listAll(),
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    /**
     * Garbage collector. Is executed after this class has completed
     * its job and the template has also been displayed.
     */
    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_profiletype_clean')) ? eval($sPlugin) : false);
    }
}
