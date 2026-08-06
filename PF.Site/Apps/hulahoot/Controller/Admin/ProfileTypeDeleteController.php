<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class ProfileTypeDeleteController extends Phpfox_Component
{
    public function process()
    {
        $service = new \Apps\Hulahoot\Service\ProfileTypeAdmin();
        $req = $this->request();
        $typeId = (int)$req->get('id');
        $type = $service->getById($typeId);

        if (!$type) {
            $this->url()->send('/admincp/hulahoot/profiletype', [], _p('hulahoot_profile_type_not_found'));
        }

        $error = null;

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                try {
                    $service->delete($typeId);

                    $this->url()->send('/admincp/hulahoot/profiletype', [], _p('hulahoot_profile_type_deleted'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $this->template()->setTitle(_p('hulahoot_delete_profile_type'))
            ->setBreadCrumb(_p('hulahoot_delete_profile_type'))
            ->assign([
                'type' => $type,
                'type_name' => _p($type['name']),
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_profiletype_delete_clean')) ? eval($sPlugin) : false);
    }
}
