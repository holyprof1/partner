<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * AdminCP "Edit Rules" form for one native subscription package. Only ever
 * writes to hulahoot_subscription_package / hulahoot_subscription_package_category
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
        $service = new \Apps\Hulahoot\Service\SubscriptionPackageAdmin();
        $req = $this->request();
        $error = null;

        $packageId = (int)$req->get('id');
        $package = $packageId ? $service->getNativePackage($packageId) : false;

        if (!$package) {
            $this->url()->send('/admincp/hulahoot/subscriptionpackage', [], _p('hulahoot_subscription_package_not_found'));
        }

        $rules = $service->getRules($packageId);
        $selectedIndustryIds = $service->getIndustryIdsForPackage($packageId);

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $rules = [
                    'purchase_limit' => $req->get('purchase_limit'),
                    'posting_limit_per_day' => $req->get('posting_limit_per_day'),
                    'posting_limit_per_month' => $req->get('posting_limit_per_month'),
                    'monthly_credits' => (int)$req->get('monthly_credits'),
                    'is_active' => $req->get('is_active') ? 1 : 0,
                ];
                $selectedIndustryIds = array_map('intval', (array)$req->get('industry_ids', []));

                try {
                    $service->saveRules($packageId, $rules, $selectedIndustryIds);

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
        // {if $selected_industry_map[$industry.category_id]} instead.
        $selectedIndustryMap = array_fill_keys($selectedIndustryIds, true);

        $this->template()->setTitle(_p('hulahoot_edit_subscription_package_rules'))
            ->setBreadCrumb(_p('hulahoot_edit_subscription_package_rules'))
            ->assign([
                'package_id' => $packageId,
                'package' => $package,
                'rules' => $rules,
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
