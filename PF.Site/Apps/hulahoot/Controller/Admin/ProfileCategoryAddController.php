<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class ProfileCategoryAddController extends Phpfox_Component
{
    public function process()
    {
        $req = $this->request();
        $categoryService = new \Apps\Hulahoot\Service\ProfileCategoryAdmin();
        $profileTypeService = new \Apps\Hulahoot\Service\ProfileTypeAdmin();
        $error = null;

        $categoryId = (int)$req->get('id');
        $category = $categoryId ? $categoryService->getById($categoryId) : null;

        if ($categoryId && !$category) {
            $this->url()->send('/admincp/hulahoot/profilecategory', [], _p('hulahoot_category_not_found'));
        }

        $profileTypeId = $category ? (int)$category['profile_type_id'] : (int)$req->get('profile_type_id');
        $profileType = $profileTypeService->getById($profileTypeId);

        if (!$profileType) {
            $this->url()->send('/admincp/hulahoot/profilecategory', [], _p('hulahoot_profile_type_not_found'));
        }

        $formValues = $category ?: [
            'parent_id' => (int)$req->get('parent_id'),
            'name' => '', 'name_url' => '', 'is_active' => 1, 'ordering' => 0,
        ];

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $formValues = [
                    'profile_type_id' => $profileTypeId,
                    'parent_id' => (int)$req->get('parent_id'),
                    'name' => (string)$req->get('name'),
                    'name_url' => (string)$req->get('name_url'),
                    'is_active' => $req->get('is_active') ? 1 : 0,
                    'ordering' => (int)$req->get('ordering'),
                ];

                try {
                    if ($categoryId) {
                        $categoryService->update($categoryId, $formValues);
                    } else {
                        $categoryId = $categoryService->create($formValues);
                    }

                    $this->url()->send('/admincp/hulahoot/profilecategory', ['profile_type_id' => $profileTypeId], _p($category ? 'hulahoot_category_updated' : 'hulahoot_category_created'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        // Top-level categories under the same type, for the Parent dropdown
        // - excludes the row being edited itself (a category can't be its
        // own parent).
        $topCategories = array_filter($categoryService->listAllForType($profileTypeId), function ($aRow) use ($categoryId) {
            return (int)$aRow['parent_id'] === 0 && (int)$aRow['category_id'] !== (int)$categoryId;
        });

        $this->template()->setTitle($category ? _p('hulahoot_edit_category') : _p('hulahoot_add_category'))
            ->setBreadCrumb($category ? _p('hulahoot_edit_category') : _p('hulahoot_add_category'))
            ->assign([
                'profile_type' => $profileType,
                'category_id' => $categoryId,
                'is_edit' => (bool)$category,
                'values' => $formValues,
                'top_categories' => array_values($topCategories),
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_profilecategory_add_clean')) ? eval($sPlugin) : false);
    }
}
