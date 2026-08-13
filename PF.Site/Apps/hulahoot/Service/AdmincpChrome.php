<?php

namespace Apps\Hulahoot\Service;

/**
 * Class AdmincpChrome
 *
 * Every native AdminCP page reaches Apps\Core\Admincp\Controller\IndexController
 * (Admincp_Component_Controller_Index::process() in the classic module),
 * which unconditionally enqueues menu.css/menu.js/admin.js/drag.js/
 * jquery.mosaicflow.min.js before dispatching to the actual page.
 * Hulahoot's AdminCP routes call Phpfox::getLib('module')->dispatch()
 * directly on their own component (see start.php's admincp/hulahoot
 * group) rather than going through that Index controller, so those
 * assets were silently never enqueued - the page's own markup still
 * rendered, but the left-hand nav menu, which depends on menu.css/
 * menu.js to lay out and expand correctly, rendered as a collapsed,
 * broken sliver instead of the real sidebar. Confirmed live 2026-08-07
 * by diffing the asset list between a native AdminCP page and a
 * Hulahoot one - only these five files were missing.
 *
 * Call apply() from every Hulahoot AdminCP controller's process(), the
 * same five files Admincp_Component_Controller_Index::process() enqueues,
 * so every Hulahoot AdminCP page gets identical chrome to every native one.
 *
 * apply() also assigns aSectionAppMenus - the horizontal "Profile Types
 * | Profile Categories | Industries | Subscription Packages" tab strip
 * (theme/adminpanel/default/template/template.html.php renders it
 * whenever aSectionAppMenus is non-empty, in a <div class="acp-header-
 * section">). That variable is normally built from menu.xml config
 * inside the same Index::process() this app's routes bypass, so - same
 * as the missing CSS/JS above - it was never assigned, meaning the tab
 * strip has never appeared on any Hulahoot AdminCP page, not just the
 * drill-down ones (confirmed live 2026-08-07 by reading
 * template.html.php's own {if !empty($aSectionAppMenus)} guard - not
 * something a fresh cache clear or per-page fix could touch). Since
 * Hulahoot's set of AdminCP sections is fixed and small, this hard-
 * codes the same four entries rather than reimplementing the XML-
 * driven menu builder for one four-item list.
 */
class AdmincpChrome
{
    /**
     * @param \Core\View|mixed $template Whatever $this->template() returns
     *        in a classic Phpfox_Component controller.
     * @param array|null $aLinks Optional override of the tab strip's
     *        [phrase => url] entries. Defaults to the "Hulahoot Profiles"
     *        app's own sections. SWESS's controllers pass self::swessLinks()
     *        instead - SWESS is registered as its own separate AdminCP app
     *        (PF.Site/Apps/hulahoot-swess) precisely so it isn't a tab
     *        buried inside Hulahoot Profiles' navigation, so it needs its
     *        own tab strip here too rather than inheriting this one.
     */
    public static function apply($template, array $aLinks = null)
    {
        $template->setHeader([
            'menu.css' => 'style_css',
            'menu.js' => 'style_script',
            'admin.js' => 'static_script',
            'drag.js' => 'static_script',
            'jquery/plugin/jquery.mosaicflow.min.js' => 'static_script',
        ]);

        $sPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        if ($aLinks === null) {
            $aLinks = [
                _p('hulahoot_admin_profile_types') => '/admincp/hulahoot/profiletype',
                _p('hulahoot_admin_profile_categories') => '/admincp/hulahoot/profilecategory',
                _p('hulahoot_admin_industries') => '/admincp/hulahoot/industry',
                _p('hulahoot_admin_subscription_packages') => '/admincp/hulahoot/subscriptionpackage',
                _p('hulahoot_admin_package_templates') => '/admincp/hulahoot/packagetemplate',
                // A dedicated single-textarea screen (Controller/Admin/
                // LandingPageController.php) over the same native Custom
                // HTML block GuestLandingContent.php reads from - the native
                // Block Manager form works too, but is cluttered with fields
                // (title, placement, access) this doesn't need.
                _p('hulahoot_admin_landing_page') => '/admincp/hulahoot/landingpage',
            ];
        }

        $aSectionAppMenus = [];
        foreach ($aLinks as $sPhrase => $sUrl) {
            // Active-tab matching only ever needs the path portion - a
            // link like the Landing Page one above carries a query
            // string, which $sPath (already stripped of its own query
            // string by parse_url() above) would never equal.
            $sUrlPath = parse_url($sUrl, PHP_URL_PATH);

            $aSectionAppMenus[$sPhrase] = [
                'url' => $sUrl,
                'is_active' => ($sPath === $sUrlPath || strpos((string)$sPath, $sUrlPath . '/') === 0),
            ];
        }

        $template->assign(['aSectionAppMenus' => $aSectionAppMenus]);
    }

    /**
     * The SWESS app's own tab strip - pass to apply()'s second argument
     * from every SWESS AdminCP controller instead of relying on the
     * default (Hulahoot Profiles') links.
     *
     * @return array
     */
    public static function swessLinks()
    {
        return [
            _p('hulahoot_admin_swess_whitelist') => '/admincp/hulahoot/swess/whitelist',
            _p('hulahoot_admin_swess_tags') => '/admincp/hulahoot/swess/tag',
            _p('hulahoot_admin_swess_approval') => '/admincp/hulahoot/swess/approval',
            _p('hulahoot_admin_swess_audit') => '/admincp/hulahoot/swess/audit',
        ];
    }
}
