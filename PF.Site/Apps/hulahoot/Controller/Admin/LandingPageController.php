<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * AdminCP "Landing Page" screen - a single big HTML textarea, nothing
 * else. Requested directly after the native Block Manager form (the
 * only other way to reach this same content) turned out to be cluttered
 * with fields that don't matter here - title, placement, access
 * checkboxes - none of which this screen touches at all (see
 * Service/GuestLandingContent.php's own note on that). This is purely a
 * friendlier front door onto the exact same :block_source row.
 */
class LandingPageController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template());

        $service = new \Apps\Hulahoot\Service\GuestLandingContent();
        $req = $this->request();
        $error = null;
        $sSaved = null;

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $sSaved = (string)$req->get('html');
                $service->setHtml($sSaved);
                $this->url()->send('/admincp/hulahoot/landingpage', [], _p('hulahoot_landing_page_saved'));
            }
        }

        $this->template()->setTitle(_p('hulahoot_admin_landing_page'))
            ->setBreadCrumb(_p('hulahoot_admin_landing_page'))
            ->assign([
                'html' => $sSaved !== null ? $sSaved : ($service->getHtml() ?? ''),
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
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_landingpage_clean')) ? eval($sPlugin) : false);
    }
}
