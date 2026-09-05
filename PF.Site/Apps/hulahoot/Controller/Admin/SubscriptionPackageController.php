<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * AdminCP list of every native Core Subscriptions package, each row showing
 * whether Hulahoot companion rules exist for it yet. No create/delete here -
 * packages themselves (title, price, billing) stay owned entirely by
 * Core Subscriptions' own AdminCP; this screen only links out to
 * "Edit Rules" (SubscriptionPackageEditController), which writes only to
 * the companion tables. See Service/SubscriptionPackageAdmin.php.
 */
class SubscriptionPackageController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\SubscriptionPackageAdmin();
        $uploadService = new \Apps\Hulahoot\Service\ImageUpload();

        $aPackages = $service->listAll();
        foreach ($aPackages as &$aPackage) {
            $aPackage['hulahoot_image_url'] = $uploadService->resolveUrl($aPackage['hulahoot_rules']['image'] ?? null);
        }
        unset($aPackage);

        $this->template()->setTitle(_p('hulahoot_admin_subscription_packages'))
            ->setBreadCrumb(_p('hulahoot_admin_subscription_packages'))
            ->assign([
                'packages' => $aPackages,
                'subscriptions_active' => Phpfox::isAppActive('Core_Subscriptions'),
            ]);
    }

    /**
     * Garbage collector. Is executed after this class has completed
     * its job and the template has also been displayed.
     */
    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_subscriptionpackage_clean')) ? eval($sPlugin) : false);
    }
}
