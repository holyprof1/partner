<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class IndustryDeleteController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\IndustryAdmin();
        $req = $this->request();
        $industryId = (int)$req->get('id');
        $industry = $service->getById($industryId);

        if (!$industry) {
            $this->url()->send('/admincp/hulahoot/industry', [], _p('hulahoot_industry_not_found'));
        }

        $error = null;

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                try {
                    $service->delete($industryId);

                    $this->url()->send('/admincp/hulahoot/industry', [], _p('hulahoot_industry_deleted'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $this->template()->setTitle(_p('hulahoot_delete_industry'))
            ->setBreadCrumb(_p('hulahoot_delete_industry'))
            ->assign([
                'industry' => $industry,
                'industry_name' => _p($industry['name']),
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_industry_delete_clean')) ? eval($sPlugin) : false);
    }
}
