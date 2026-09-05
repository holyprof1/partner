<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class ProfileCategoryController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $profileTypeService = new \Apps\Hulahoot\Service\ProfileTypeAdmin();
        $profileTypeId = (int)$this->request()->get('profile_type_id');

        if (!$profileTypeId) {
            $this->template()->setTitle(_p('hulahoot_admin_profile_categories'))
                ->setBreadCrumb(_p('hulahoot_admin_profile_categories'))
                ->assign([
                    'types' => $profileTypeService->listAll(),
                ]);

            return;
        }

        $profileType = $profileTypeService->getById($profileTypeId);

        if (!$profileType) {
            $this->url()->send('/admincp/hulahoot/profilecategory', [], _p('hulahoot_profile_type_not_found'));
        }

        $categoryService = new \Apps\Hulahoot\Service\ProfileCategoryAdmin();

        $title = _p('hulahoot_admin_profile_categories') . ' - ' . _p($profileType['name']);

        $this->template()->setTitle($title)
            ->setBreadCrumb($title)
            ->assign([
                'profile_type' => $profileType,
                'categories' => $categoryService->listAllForType($profileTypeId),
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    /**
     * Garbage collector. Is executed after this class has completed
     * its job and the template has also been displayed.
     */
    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_profilecategory_clean')) ? eval($sPlugin) : false);
    }
}
