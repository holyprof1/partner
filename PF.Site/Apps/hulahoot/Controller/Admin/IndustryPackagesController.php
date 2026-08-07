<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * AdminCP "click an Industry, see its packages" screen - lists every
 * package currently assigned to one Industry (with a link into the full
 * Edit Rules form for each), a quick picker to assign an existing
 * package without opening its full form, a quick unassign per row, a
 * "Create from Template" picker (Service\PackageTemplateAdmin::
 * createPackageFromTemplate() - a brand new, fully independent package
 * seeded from one of AdminCP's Default Packages), and a link out to
 * Core Subscriptions' own AdminCP to create a package completely from
 * scratch (price/billing can only ever be created there - see
 * Service/SubscriptionPackageAdmin.php's own class docblock).
 *
 * Assign/unassign only ever writes to hulahoot_subscription_package_industry
 * via Service/SubscriptionPackageAdmin::assignIndustryToPackage() /
 * removeIndustryFromPackage() - never touches a package's rules, its
 * other Industry links, or the native package itself.
 */
class IndustryPackagesController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $industryService = new \Apps\Hulahoot\Service\IndustryAdmin();
        $packageService = new \Apps\Hulahoot\Service\SubscriptionPackageAdmin();
        $templateService = new \Apps\Hulahoot\Service\PackageTemplateAdmin();
        $req = $this->request();
        $error = null;

        $industryId = (int)$req->get('id');
        $industry = $industryId ? $industryService->getById($industryId) : false;

        if (!$industry) {
            $this->url()->send('/admincp/hulahoot/industry', [], _p('hulahoot_industry_not_found'));
        }

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $iRemovePackageId = (int)$req->get('remove_package_id');
                $iAssignPackageId = (int)$req->get('assign_package_id');
                $iTemplateId = (int)$req->get('create_from_template_id');

                if ($iRemovePackageId) {
                    $packageService->removeIndustryFromPackage($iRemovePackageId, $industryId);
                    $this->url()->send('/admincp/hulahoot/industry/packages', ['id' => $industryId], _p('hulahoot_package_unassigned'));
                } elseif ($iAssignPackageId) {
                    $packageService->assignIndustryToPackage($iAssignPackageId, $industryId);
                    $this->url()->send('/admincp/hulahoot/industry/packages', ['id' => $industryId], _p('hulahoot_package_assigned'));
                } elseif ($iTemplateId) {
                    try {
                        $templateService->createPackageFromTemplate($iTemplateId, $industryId);
                        $this->url()->send('/admincp/hulahoot/industry/packages', ['id' => $industryId], _p('hulahoot_package_created_from_template'));
                    } catch (\InvalidArgumentException $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }

        $this->template()->setTitle(_p('hulahoot_industry_packages_title', ['name' => _p($industry['name'])]))
            ->setBreadCrumb(_p('hulahoot_admin_industries'), '/admincp/hulahoot/industry')
            ->setBreadCrumb(_p($industry['name']))
            ->assign([
                'industry' => $industry,
                'packages' => $packageService->getPackagesForIndustryAdmin($industryId),
                'unassigned_packages' => $packageService->getUnassignedPackages($industryId),
                'templates' => $templateService->listActive(),
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_industrypackages_clean')) ? eval($sPlugin) : false);
    }
}
