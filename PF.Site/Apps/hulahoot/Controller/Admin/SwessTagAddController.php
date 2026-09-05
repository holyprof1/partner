<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class SwessTagAddController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template(), \Apps\Hulahoot\Service\AdmincpChrome::swessLinks());

        $service = new \Apps\Hulahoot\Service\Swess();
        $req = $this->request();
        $error = null;

        $iTagId = (int)$req->get('id');
        $aTag = $iTagId ? $service->getTagById($iTagId) : null;

        if ($iTagId && !$aTag) {
            $this->url()->send('/admincp/hulahoot/swess/tag', [], _p('hulahoot_swess_tag_not_found'));
        }

        $aFormValues = $aTag ?: ['name' => '', 'description' => '', 'is_active' => 1, 'ordering' => 0];

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $aFormValues = [
                    'name' => (string)$req->get('name'),
                    'description' => (string)$req->get('description'),
                    'is_active' => $req->get('is_active') ? 1 : 0,
                    'ordering' => (int)$req->get('ordering'),
                ];

                try {
                    if ($iTagId) {
                        $service->updateTag($iTagId, $aFormValues);
                    } else {
                        $iTagId = $service->createTag($aFormValues);
                    }

                    $this->url()->send('/admincp/hulahoot/swess/tag', [], _p($aTag ? 'hulahoot_swess_tag_updated' : 'hulahoot_swess_tag_created'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $this->template()->setTitle($aTag ? _p('hulahoot_edit_swess_tag') : _p('hulahoot_add_swess_tag'))
            ->setBreadCrumb($aTag ? _p('hulahoot_edit_swess_tag') : _p('hulahoot_add_swess_tag'))
            ->assign([
                'tag_id' => $iTagId,
                'is_edit' => (bool)$aTag,
                'values' => $aFormValues,
                'error' => $error,
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_swess_tag_add_clean')) ? eval($sPlugin) : false);
    }
}
