<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class IndustryController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\IndustryAdmin();
        $uploadService = new \Apps\Hulahoot\Service\ImageUpload();

        $aIndustries = $service->listAll();
        foreach ($aIndustries as &$aIndustry) {
            $aIndustry['thumbnail_url'] = $uploadService->resolveUrl($aIndustry['thumbnail']);
        }
        unset($aIndustry);

        $this->template()->setTitle(_p('hulahoot_admin_industries'))
            ->setBreadCrumb(_p('hulahoot_admin_industries'))
            ->assign([
                'industries' => $aIndustries,
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    /**
     * Garbage collector. Is executed after this class has completed
     * its job and the template has also been displayed.
     */
    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_industry_clean')) ? eval($sPlugin) : false);
    }
}
