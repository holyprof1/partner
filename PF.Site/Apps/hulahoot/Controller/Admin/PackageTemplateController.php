<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * AdminCP "Default Packages" list - the reusable template library an
 * Industry's "Create from Template" picker draws from. See
 * Service/PackageTemplateAdmin.php's own docblock for the full picture.
 */
class PackageTemplateController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\PackageTemplateAdmin();

        $this->template()->setTitle(_p('hulahoot_admin_package_templates'))
            ->setBreadCrumb(_p('hulahoot_admin_package_templates'))
            ->assign([
                'templates' => $service->listAll(),
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    /**
     * Garbage collector. Is executed after this class has completed
     * its job and the template has also been displayed.
     */
    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_packagetemplate_clean')) ? eval($sPlugin) : false);
    }
}
