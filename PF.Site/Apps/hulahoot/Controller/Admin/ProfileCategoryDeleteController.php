<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class ProfileCategoryDeleteController extends Phpfox_Component
{
    public function process()
    {
        $categoryService = new \Apps\Hulahoot\Service\ProfileCategoryAdmin();
        $req = $this->request();
        $categoryId = (int)$req->get('id');
        $category = $categoryService->getById($categoryId);

        if (!$category) {
            $this->url()->send('/admincp/hulahoot/profilecategory', [], _p('hulahoot_category_not_found'));
        }

        $error = null;

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                try {
                    $categoryService->delete($categoryId);

                    $this->url()->send('/admincp/hulahoot/profilecategory', ['profile_type_id' => $category['profile_type_id']], _p('hulahoot_category_deleted'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $this->template()->setTitle(_p('hulahoot_delete_category'))
            ->setBreadCrumb(_p('hulahoot_delete_category'))
            ->assign([
                'category' => $category,
                'category_name' => _p($category['name']),
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_profilecategory_delete_clean')) ? eval($sPlugin) : false);
    }
}
