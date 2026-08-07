<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class PackageTemplateDeleteController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\PackageTemplateAdmin();
        $req = $this->request();
        $templateId = (int)$req->get('id');
        $template = $service->getById($templateId);

        if (!$template) {
            $this->url()->send('/admincp/hulahoot/packagetemplate', [], _p('hulahoot_template_not_found'));
        }

        $error = null;

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                try {
                    $service->delete($templateId);

                    $this->url()->send('/admincp/hulahoot/packagetemplate', [], _p('hulahoot_template_deleted'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $this->template()->setTitle(_p('hulahoot_delete_template'))
            ->setBreadCrumb(_p('hulahoot_admin_package_templates'), '/admincp/hulahoot/packagetemplate')
            ->setBreadCrumb(_p('hulahoot_delete_template'))
            ->assign([
                'template' => $template,
                'template_name' => $template['name'],
                'error' => $error,
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    /**
     * Garbage collector. Is executed after this class has completed
     * its job and the template has also been displayed.
     */
    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_packagetemplate_delete_clean')) ? eval($sPlugin) : false);
    }
}
