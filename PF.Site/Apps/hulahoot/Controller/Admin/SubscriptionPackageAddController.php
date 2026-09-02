<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * AdminCP "Edit Package" form for one native subscription package. Only
 * ever writes to hulahoot_subscription_package /
 * hulahoot_subscription_package_industry / hulahoot_subscription_package_feature
 * (via Service/SubscriptionPackageAdmin::saveRules()) - never touches
 * subscribe_package itself, so the native package's own title/price/billing
 * screen (Core Subscriptions' AdminCP) remains the only place those change.
 *
 * Named "Add" (not "Edit") to match the "{resource}-add" -> "{resource}-form"
 * component/template naming convention already established by
 * ProfileTypeAddController/ProfileCategoryAddController - even though this
 * controller only ever edits an existing companion row (packages themselves
 * are never created here). The URL stays /subscriptionpackage/edit since
 * that's what it actually does; only the internal component name follows
 * the convention.
 */
class SubscriptionPackageAddController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\SubscriptionPackageAdmin();
        $uploadService = new \Apps\Hulahoot\Service\ImageUpload();
        $req = $this->request();
        $error = null;

        $packageId = (int)$req->get('id');
        $package = $packageId ? $service->getNativePackage($packageId) : false;

        if (!$package) {
            $this->url()->send('/admincp/hulahoot/subscriptionpackage', [], _p('hulahoot_subscription_package_not_found'));
        }

        $rules = $service->getRules($packageId);
        $selectedIndustryIds = $service->getIndustryIdsForPackage($packageId);
        $featureTexts = array_column($service->getFeaturesForPackage($packageId), 'feature_text');

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $rules = [
                    'display_name' => $req->get('display_name'),
                    'subtitle' => $req->get('subtitle'),
                    'description' => $req->get('description'),
                    'badge_text' => $req->get('badge_text'),
                    'icon' => $req->get('icon'),
                    'accent_color' => $req->get('accent_color'),
                    'button_text' => $req->get('button_text'),
                    'ordering' => $req->get('ordering'),
                    'purchase_limit' => $req->get('purchase_limit'),
                    'campaign_limit' => $req->get('campaign_limit'),
                    'posting_limit_per_day' => $req->get('posting_limit_per_day'),
                    'posting_limit_per_month' => $req->get('posting_limit_per_month'),
                    'monthly_credits' => (int)$req->get('monthly_credits'),
                    'swess_enabled' => $req->get('swess_enabled') ? 1 : 0,
                    'is_active' => $req->get('is_active') ? 1 : 0,
                    'is_renewable' => $req->get('is_renewable') ? 1 : 0,
                    'is_locked_pending_admin' => $req->get('is_locked_pending_admin') ? 1 : 0,
                    'is_open' => $req->get('is_open') ? 1 : 0,
                ];
                $selectedIndustryIds = array_map('intval', (array)$req->get('industry_ids', []));
                // One feature per line - the simplest possible "add /
                // remove / reorder" UI: typing a new line adds a feature,
                // deleting one removes it, and reordering the lines
                // reorders the list. No JS-driven repeater needed.
                $featureTexts = preg_split('/\r\n|\r|\n/', (string)$req->get('features', ''));

                try {
                    $sImagePath = $uploadService->upload('image');

                    $service->saveRules($packageId, $rules, $selectedIndustryIds, $featureTexts, $sImagePath);

                    $this->url()->send('/admincp/hulahoot/subscriptionpackage', [], _p('hulahoot_subscription_package_rules_updated'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        // Smarty's {if in_array(...)} isn't reliably available in this
        // template pipeline (see the other admincp templates - equality
        // checks are always precomputed, never left to inline function
        // calls) - a lookup map lets the template do
        // {if $selected_industry_map[$industry.industry_id]} instead.
        $selectedIndustryMap = array_fill_keys($selectedIndustryIds, true);

        $this->template()->setTitle(_p('hulahoot_edit_subscription_package_rules'))
            ->setBreadCrumb(_p('hulahoot_edit_subscription_package_rules'))
            ->assign([
                'package_id' => $packageId,
                'package' => $package,
                'rules' => $rules,
                'image_url' => $uploadService->resolveUrl($rules['image'] ?? null),
                'features_text' => implode("\n", array_filter($featureTexts, function ($s) {
                    return trim((string)$s) !== '';
                })),
                'industries' => $service->getAllIndustries(),
                'selected_industry_map' => $selectedIndustryMap,
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_subscriptionpackage_add_clean')) ? eval($sPlugin) : false);
    }
}
