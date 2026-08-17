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
            _p('hulahoot_admin_industries') => 'hulahoot.industry',
            _p('hulahoot_admin_subscription_packages') => 'hulahoot.subscriptionpackage',
            _p('hulahoot_admin_package_templates') => 'hulahoot.packagetemplate',
            _p('hulahoot_admin_landing_page') => 'hulahoot.landingpage',
            // SWESS is intentionally NOT listed here - it's registered as
            // its own separate AdminCP app (PF.Site/Apps/hulahoot-swess/
            // Install.php, apps_id 'HulahootSwess') precisely so it shows
            // up as its own entry in /admincp/app/ instead of being a tab
            // buried inside this one. See that file's own docblock.
        ];

        // Table::install() runs for each of these, in this order, every
        // time processInstall() runs - idempotent create-or-diff-upgrade.
        // See docs/MigrationPlan.md "Implementation order".
        //
        // Industry (Phase 2): the post-login marketplace users browse -
        // deliberately independent of ProfileType/ProfileCategory, which
        // exist only for registration/profile classification. See
        // Installation/Database/Industry.php's docblock and
        // docs/PHASE_2_SUBSCRIPTION.md.
        //
        // SubscriptionPackage / SubscriptionPackageIndustry /
        // SubscriptionPackageFeature (Phase 2): companion overlay tables
        // for Core Subscriptions' native subscribe_package - no core
        // table touched, no price/title/billing data duplicated.
        // SubscriptionPackageIndustry replaces the earlier
        // SubscriptionPackageCategory (retired - see git history; it
        // pointed at ProfileCategory, which is the wrong concept for a
        // package's storefront scoping).
        $this->database = [
            'ProfileType',
            'ProfileCategory',
            'Profile',
            'Industry',
            'SubscriptionPackage',
            'SubscriptionPackageIndustry',
            'SubscriptionPackageFeature',
            'PackageTemplate',
            'ExpiryReminder',
            // SWESS foundation (Partner Portal architecture phase only -
            // see Service/Swess.php's docblock). Whitelist before the
            // tables that reference whitelist_id; Tag before the
            // identity/tag junction; ApprovedIdentity before that same
            // junction and before AuditLog (which merely references it,
            // no hard FK either way, but keeping creation order sensible).
            'Swess',
            'SwessTag',
            'SwessApprovedIdentity',
            'SwessIdentityTag',
            'SwessAuditLog',
            'SwessPost',
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

        // SWESS lifecycle notifications, via Core\App\App's declarative
        // $notifications property - the same extension point Ync_Blogs
        // uses for its own comment notification ("__comment"), reached at
        // send time through the global notify($app_id, $key_name, ...)
        // helper (PF.Base/start.php) rather than a direct call to
        // Notification_Service_Process, so every SWESS notification
        // resolves its display copy from here in one place. See
        // Service/Swess.php's notify() call sites for exactly when each
        // of these fires.
        $this->notifications = [
            'whitelist_enabled' => [
                'message' => 'Your SWESS access has been enabled',
                'url' => '/hulahoot/swess',
                'icon' => 'check-circle',
            ],
            'whitelist_disabled' => [
                'message' => 'Your SWESS access has been disabled',
                'url' => '/hulahoot/swess',
                'icon' => 'ban',
            ],
            'identity_approved' => [
                'message' => 'A publishing identity was approved for your SWESS account',
                'url' => '/hulahoot/swess',
                'icon' => 'id-badge',
            ],
            'identity_revoked' => [
                'message' => 'A publishing identity was revoked from your SWESS account',
                'url' => '/hulahoot/swess',
                'icon' => 'id-badge',
            ],
            'post_submitted' => [
                'message' => 'Your SWESS post was submitted for review',
                'url' => '/hulahoot/swess/posts',
                'icon' => 'paper-plane',
            ],
            'post_approved' => [
                'message' => 'Your SWESS post was approved',
                'url' => '/hulahoot/swess/posts',
                'icon' => 'check',
            ],
            'post_rejected' => [
                'message' => 'Your SWESS post was rejected',
                'url' => '/hulahoot/swess/posts',
                'icon' => 'times',
            ],
            'post_published' => [
                'message' => 'Your scheduled SWESS post was published',
                'url' => '/hulahoot/swess/posts',
                'icon' => 'bullhorn',
            ],
            'post_failed' => [
                'message' => 'Your scheduled SWESS post failed to publish',
                'url' => '/hulahoot/swess/posts',
                'icon' => 'exclamation-triangle',
            ],
        ];
    }
}
