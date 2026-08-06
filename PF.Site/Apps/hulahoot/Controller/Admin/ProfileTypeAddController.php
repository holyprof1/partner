<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class ProfileTypeAddController extends Phpfox_Component
{
    public function process()
    {
        $service = new \Apps\Hulahoot\Service\ProfileTypeAdmin();
        $req = $this->request();
        $error = null;

        $typeId = (int)$req->get('id');
        $type = $typeId ? $service->getById($typeId) : null;

        if ($typeId && !$type) {
            $this->url()->send('/admincp/hulahoot/profiletype', [], _p('hulahoot_profile_type_not_found'));
        }

        $formValues = $type ?: [
            'name' => '', 'name_url' => '', 'description' => '', 'icon' => '',
            'requires_category' => 0, 'is_default' => 0, 'is_individual' => 0,
            'is_active' => 1, 'is_user_creatable' => 1, 'ordering' => 0,
        ];

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $formValues = [
                    'name' => (string)$req->get('name'),
                    'name_url' => (string)$req->get('name_url'),
                    'description' => (string)$req->get('description'),
                    'icon' => (string)$req->get('icon'),
                    'requires_category' => $req->get('requires_category') ? 1 : 0,
                    'is_default' => $req->get('is_default') ? 1 : 0,
                    'is_individual' => $req->get('is_individual') ? 1 : 0,
                    'is_active' => $req->get('is_active') ? 1 : 0,
                    'is_user_creatable' => $req->get('is_user_creatable') ? 1 : 0,
                    'ordering' => (int)$req->get('ordering'),
                ];

                try {
                    if ($typeId) {
                        $service->update($typeId, $formValues);
                    } else {
                        $typeId = $service->create($formValues);
                    }

                    $this->url()->send('/admincp/hulahoot/profiletype', [], _p($type ? 'hulahoot_profile_type_updated' : 'hulahoot_profile_type_created'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $this->template()->setTitle($type ? _p('hulahoot_edit_profile_type') : _p('hulahoot_add_profile_type'))
            ->setBreadCrumb($type ? _p('hulahoot_edit_profile_type') : _p('hulahoot_add_profile_type'))
            ->assign([
                'type_id' => $typeId,
                'is_edit' => (bool)$type,
                'values' => $formValues,
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_profiletype_add_clean')) ? eval($sPlugin) : false);
    }
}
