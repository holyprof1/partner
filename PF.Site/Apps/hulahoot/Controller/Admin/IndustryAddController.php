<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class IndustryAddController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\IndustryAdmin();
        $uploadService = new \Apps\Hulahoot\Service\ImageUpload();
        $req = $this->request();
        $error = null;

        $industryId = (int)$req->get('id');
        $industry = $industryId ? $service->getById($industryId) : null;

        if ($industryId && !$industry) {
            $this->url()->send('/admincp/hulahoot/industry', [], _p('hulahoot_industry_not_found'));
        }

        $formValues = $industry ?: [
            'name' => '', 'slug' => '', 'description' => '', 'icon' => '',
            'is_active' => 1, 'sort_order' => 0, 'banner' => null, 'thumbnail' => null,
        ];

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $formValues = [
                    'name' => (string)$req->get('name'),
                    'slug' => (string)$req->get('slug'),
                    'description' => (string)$req->get('description'),
                    'icon' => (string)$req->get('icon'),
                    'is_active' => $req->get('is_active') ? 1 : 0,
                    'sort_order' => (int)$req->get('sort_order'),
                ];

                try {
                    $sBannerPath = $uploadService->upload('banner');
                    $sThumbnailPath = $uploadService->upload('thumbnail');

                    if ($industryId) {
                        $service->update($industryId, $formValues, $sBannerPath, $sThumbnailPath);
                    } else {
                        $industryId = $service->create($formValues, $sBannerPath, $sThumbnailPath);
                    }

                    $this->url()->send('/admincp/hulahoot/industry', [], _p($industry ? 'hulahoot_industry_updated' : 'hulahoot_industry_created'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                    $formValues['banner'] = $industry['banner'] ?? null;
                    $formValues['thumbnail'] = $industry['thumbnail'] ?? null;
                }
            }
        }

        $this->template()->setTitle($industry ? _p('hulahoot_edit_industry') : _p('hulahoot_add_industry'))
            ->setBreadCrumb($industry ? _p('hulahoot_edit_industry') : _p('hulahoot_add_industry'))
            ->assign([
                'industry_id' => $industryId,
                'is_edit' => (bool)$industry,
                'values' => $formValues,
                'banner_url' => $uploadService->resolveUrl($formValues['banner'] ?? null),
                'thumbnail_url' => $uploadService->resolveUrl($formValues['thumbnail'] ?? null),
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_industry_add_clean')) ? eval($sPlugin) : false);
    }
}
