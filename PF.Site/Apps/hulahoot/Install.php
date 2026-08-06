<?php

namespace Apps\Hulahoot;

use Core\App;
use Phpfox;

/**
 * Class Install
 *
 * Hulahoot Profile Type database foundation (Sprint 2A). Database-only:
 * creates hulahoot_profile_type, hulahoot_profile_category, hulahoot_profile
 * and seeds the three default Profile Types. Does not touch registration,
 * login, AdminCP, or any existing phpFox table/module - see
 * docs/MigrationPlan.md for what later sprints will add on top of this.
 *
 * @author  HolyProf
 * @version 1.0.0
 * @package Apps\Hulahoot
 */
class Install extends App\App
{
    private $_app_phrases = [];

    protected function setId()
    {
        $this->id = 'Hulahoot';
    }

    /**
     * start_support_version asserts a minimum ("requires phpFox 4.8.14 or
     * newer") - safe to pin to the current version, since that's exactly
     * what Core\App\App::isValid() checks it against.
     *
     * end_support_version is intentionally left empty, NOT pinned to the
     * current version. Core\App\App::isCompatible() treats an empty
     * end_support_version as open-ended forward compatibility; pinning it
     * to today's exact version (the pattern Apps\PHPfox_Core\Install uses)
     * would make isCompatible() start returning false the moment phpFox
     * is upgraded to any newer release, even though nothing about this
     * module would actually be broken. PHPfox_Core is exempt from this
     * via App::isCore() - this module isn't, so it doesn't copy that part
     * of the pattern.
     */
    protected function setSupportVersion()
    {
        $this->start_support_version = Phpfox::getVersion();
        $this->end_support_version = '';
    }

    protected function setAlias()
    {
        // Core\App\App::isValid() requires an alias the moment $this->menu
        // is non-empty ("We have to have alias for remove/disable menu when
        // uninstall/disable") - registering the My Profiles menu item below
        // needs this set, unlike the rest of this App which still has no
        // legacy module/admincp/block/component footprint otherwise.
        $this->alias = 'hulahoot';
    }

    protected function setName()
    {
        $this->name = 'Hulahoot Profiles';
    }

    protected function setVersion()
    {
        $this->version = '1.0.0';
    }

    protected function setSettings()
    {
        // No admin-facing settings in Sprint 2A (database foundation only).
    }

    protected function setUserGroupSettings()
    {
    }

    protected function setComponent()
    {
    }

    protected function setComponentBlock()
    {
    }

    protected function setPhrase()
    {
        $this->phrase = $this->_app_phrases;
    }

    protected function setOthers()
    {
        $this->_publisher = 'HolyProf';
        $this->_publisher_url = 'https://www.hulahoot.com/';
        $this->_apps_dir = 'hulahoot';

        // Standard App AdminCP registration (Core\App\App::$admincp_route /
        // $admincp_menu), the same mechanism Core_Subscriptions,
        // Core_Marketplace and Ync_Blogs use for their own AdminCP pages -
        // NOT a hand-rolled admincp_get_main_menus hook (removed; no other
        // installed app uses that hook, and it left this app's own
        // /admincp/app/?id=Hulahoot page empty, with the two working pages
        // reachable only through a manually-injected sidebar entry stuck
        // after Logout). Values are dot-separated path segments after
        // 'admincp.', matching e.g. Core_Subscriptions' 'subscribe.list' =>
        // /admincp/subscribe/list/ - resolves here to the same
        // /admincp/hulahoot/profiletype(.../profilecategory) routes already
        // registered in start.php, so no route changes were needed.
        $this->admincp_route = 'admincp.hulahoot.profiletype';
        $this->admincp_menu = [
            _p('hulahoot_admin_profile_types') => 'hulahoot.profiletype',
            _p('hulahoot_admin_profile_categories') => 'hulahoot.profilecategory',
            _p('hulahoot_admin_subscription_packages') => 'hulahoot.subscriptionpackage',
        ];

        // Table::install() runs for each of these, in this order, every
        // time processInstall() runs - idempotent create-or-diff-upgrade.
        // See docs/MigrationPlan.md "Implementation order".
        //
        // SubscriptionPackage / SubscriptionPackageCategory (Phase 2):
        // companion overlay tables for Core Subscriptions' native
        // subscribe_package - no core table touched, no price/title/
        // billing data duplicated. See docs/PHASE_2_SUBSCRIPTION.md.
        $this->database = [
            'ProfileType',
            'ProfileCategory',
            'Profile',
            'SubscriptionPackage',
            'SubscriptionPackageCategory',
        ];

        // Registers a real phpfox_menu row via the sanctioned App
        // extension point (Admincp_Service_Menu_Process::importFromApp(),
        // called from processInstall() - no core file touched). Note: the
        // active theme's account dropdown is a hardcoded core template
        // that doesn't read phpfox_menu's 'profile.my' connection at all
        // (verified directly), so this alone won't make the link visible
        // in the current theme - it's registered anyway so the app stays
        // correctly wired into phpFox's own extension system, and so it
        // starts working the moment that theme's dropdown (or a future
        // theme) becomes menu-driven. See the in-app entry points added
        // separately for how users can actually find the feature today.
        $this->menu = (object)[
            'url' => 'my-profiles',
            'location' => 'profile.my',
            'phrase_var_name' => 'hulahoot_my_profiles',
            'ordering' => 5,
        ];
    }
}
