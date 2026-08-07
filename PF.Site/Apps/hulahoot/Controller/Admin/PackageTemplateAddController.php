<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * AdminCP "Add/Edit Default Package [template]" form - add (no id) or
 * edit (id present), same form for both, matching the convention every
 * other *AddController in this app uses.
 */
class PackageTemplateAddController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\PackageTemplateAdmin();
        $req = $this->request();
        $error = null;

        $templateId = (int)$req->get('id');
        $template = $templateId ? $service->getById($templateId) : false;

        if ($templateId && !$template) {
            $this->url()->send('/admincp/hulahoot/packagetemplate', [], _p('hulahoot_template_not_found'));
        }

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $data = [
                    'name' => $req->get('name'),
                    'description' => $req->get('description'),
                    'default_cost' => $req->get('default_cost'),
                    'recurring_period' => $req->get('recurring_period'),
                    'subtitle' => $req->get('subtitle'),
                    'badge_text' => $req->get('badge_text'),
                    'accent_color' => $req->get('accent_color'),
                    'button_text' => $req->get('button_text'),
                    'monthly_credits' => $req->get('monthly_credits'),
                    'purchase_limit' => $req->get('purchase_limit'),
                    'campaign_limit' => $req->get('campaign_limit'),
                    'posting_limit_per_day' => $req->get('posting_limit_per_day'),
                    'posting_limit_per_month' => $req->get('posting_limit_per_month'),
                    'features_text' => $req->get('features_text'),
                    'ordering' => $req->get('ordering'),
                    'is_active' => $req->get('is_active') ? 1 : 0,
                ];

                try {
                    if ($templateId) {
                        $service->update($templateId, $data);
                        $this->url()->send('/admincp/hulahoot/packagetemplate', [], _p('hulahoot_template_updated'));
                    } else {
                        $service->create($data);
                        $this->url()->send('/admincp/hulahoot/packagetemplate', [], _p('hulahoot_template_created'));
                    }
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                    $template = array_merge($template ?: ['template_id' => 0], $data);
                }
            }
        }

        $this->template()->setTitle($templateId ? _p('hulahoot_edit_template') : _p('hulahoot_add_template'))
            ->setBreadCrumb(_p('hulahoot_admin_package_templates'), '/admincp/hulahoot/packagetemplate')
            ->setBreadCrumb($templateId ? _p('hulahoot_edit_template') : _p('hulahoot_add_template'))
            ->assign([
                'template_id' => $templateId,
                'template' => $template ?: [
                    'name' => '', 'description' => '', 'default_cost' => 0, 'recurring_period' => 1,
                    'subtitle' => '', 'badge_text' => '', 'accent_color' => '', 'button_text' => '',
                    'monthly_credits' => 0, 'purchase_limit' => null, 'campaign_limit' => null,
                    'posting_limit_per_day' => null, 'posting_limit_per_month' => null,
                    'features_text' => '', 'ordering' => 0, 'is_active' => 1,
                ],
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_packagetemplate_add_clean')) ? eval($sPlugin) : false);
    }
}
