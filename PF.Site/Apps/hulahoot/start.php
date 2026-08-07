<?php
/**
 * Hulahoot Profile Management routes.
 *
 * Uses phpFox's modern route system (Core\Route, via the global route()/
 * group() helpers) rather than a legacy PF.Base/module controller, so this
 * stays inside the Hulahoot App tree like everything else built so far -
 * see docs/ArchitectureComplianceReport.md and docs/FutureExtensionGuide.md
 * for why new functionality lives here, not under PF.Base/module.
 *
 * Every route below requires a logged-in Master Account (auth()->membersOnly())
 * and only ever reads/writes hulahoot_profile through
 * Apps\Hulahoot\Service\Profile - no route queries the database directly.
 *
 * The multi-profile user experience (this entire route group) was removed
 * from the UI per product direction - Businesses/Organizations now use
 * phpFox Pages instead - while every underlying service/table/migration is
 * deliberately left intact for possible future use. See
 * docs/ProfileOwnership.md. Each route below now redirects home as its
 * very first statement rather than being deleted, so the original logic
 * beneath is preserved verbatim and would resume working immediately if
 * this guard line were ever removed. phpFox's route()/group() helpers have
 * no shared "before" middleware (confirmed by reading PF.Base/start.php),
 * so this can't be done once for the whole group - it's one line per route.
 */

group('/my-profiles', function () {

    // GET /my-profiles - every profile the current Master Account owns,
    // as cards, with the primary one clearly marked.
    route('/', function () {
        return url()->send('');

        auth()->membersOnly();

        $service = new \Apps\Hulahoot\Service\Profile();
        $profiles = $service->getByUserIdWithDetails(\Phpfox::getUserId());

        title(_p('hulahoot_my_profiles'));

        return view('my-profiles.html', [
            'profiles' => $profiles,
            'csrf_token' => \Phpfox::getService('log.session')->getToken(),
        ]);
    });

    // GET/POST /my-profiles/create - any active Profile Type, individual
    // or organization. Registration is the only place classification is
    // restricted to individual types (see hooks/user.template_default_
    // block_register_step1_4.php) - once an account exists, the profile
    // management system can add any type. One page: a type dropdown, a
    // name field, and a category/subcategory dropdown pair that filters
    // client-side (inline script in create-profile.html) - the same
    // validation still runs server-side in Service\Profile::create()
    // regardless of what the browser sent.
    route('/create', function () {
        return url()->send('');

        auth()->membersOnly();

        $service = new \Apps\Hulahoot\Service\Profile();
        $userId = \Phpfox::getUserId();
        $req = request();
        $error = null;

        // Active AND is_user_creatable = 1 types only - individual and
        // organization alike, but excluding anything an administrator has
        // reserved for some other assignment path (AdminCP Profile Types
        // screen). Registration is unaffected by this flag - it's the only
        // place classification is restricted, and it uses
        // getActiveIndividualProfileTypes() (see hooks/user.template_
        // default_block_register_step1_4.php), not this method.
        $creatableTypes = $service->getUserCreatableProfileTypes();

        $typeId = null;
        $categoryId = null;
        $subcategoryId = null;
        $profileName = null;

        // NOTE: isPost() in this framework means "an AJAX-marked POST"
        // (Phpfox_Request::isPost() also requires the is_ajax_post flag) -
        // this is a plain HTML form submission, so method() is checked
        // directly instead.
        if ($req->method() === 'POST') {
            $typeId = (int)$req->get('profile_type_id');
            $categoryId = $req->get('category_id') ? (int)$req->get('category_id') : null;
            $subcategoryId = $req->get('subcategory_id') ? (int)$req->get('subcategory_id') : null;
            $profileName = trim((string)$req->get('profile_name'));

            if ($req->get('hulahoot_token') !== \Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } elseif ($profileName === '') {
                $error = _p('hulahoot_profile_name_required');
            } else {
                try {
                    $service->create($userId, $typeId, $categoryId, $subcategoryId, null, $profileName);

                    return url()->send('/my-profiles', [], _p('hulahoot_profile_created'));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        list($categoriesByType, $subcategoriesByCategory) = $service->getCategoryMapsForJs();

        title(_p('hulahoot_create_new_profile'));

        return view('create-profile.html', [
            'types' => $creatableTypes,
            'selected_type_id' => $typeId,
            'selected_category_id' => $categoryId,
            'selected_subcategory_id' => $subcategoryId,
            'profile_name' => $profileName,
            'categories_by_type_json' => json_encode($categoriesByType),
            'subcategories_by_category_json' => json_encode($subcategoriesByCategory),
            'error' => $error,
            'csrf_token' => \Phpfox::getService('log.session')->getToken(),
        ]);
    });

    // POST /my-profiles/switch - make a different one of the user's own
    // profiles primary AND make the Master Account's displayed identity
    // (name/avatar/cover) match it everywhere phpFox itself renders that
    // account. Goes through Service\ActiveIdentity::switchTo() rather
    // than calling Service\Profile::setPrimary() directly - setPrimary()
    // only flips is_primary (see its own docblock), switchTo() is the one
    // place that combined operation lives. See docs/ActiveProfileIdentity.md.
    route('/switch', function () {
        return url()->send('');

        auth()->membersOnly();

        if (request()->method() !== 'POST') {
            return url()->send('/my-profiles');
        }

        if (request()->get('hulahoot_token') !== \Phpfox::getService('log.session')->getToken()) {
            return url()->send('/my-profiles', [], _p('hulahoot_invalid_token'));
        }

        $service = new \Apps\Hulahoot\Service\ActiveIdentity();
        $userId = \Phpfox::getUserId();
        $profileId = (int)request()->get('profile_id');

        try {
            $service->switchTo($userId, $profileId);

            return url()->send('/my-profiles', [], _p('hulahoot_profile_switched'));
        } catch (\InvalidArgumentException $e) {
            return url()->send('/my-profiles', [], $e->getMessage());
        }
    });

    // GET/POST /my-profiles/edit?id=X - rename any profile, and for types
    // that use one (requires_category = 1), change its category/subcategory.
    // Individual profiles get the name field only, not redirected away
    // empty-handed the way earlier builds of this route did.
    route('/edit', function () {
        return url()->send('');

        auth()->membersOnly();

        $service = new \Apps\Hulahoot\Service\Profile();
        $userId = \Phpfox::getUserId();
        $req = request();

        $profileId = (int)$req->get('id');
        $profile = $service->getById($profileId);

        if (!$profile || (int)$profile['user_id'] !== $userId) {
            return url()->send('/my-profiles', [], _p('hulahoot_profile_not_found'));
        }

        $profileType = $service->getProfileType((int)$profile['profile_type_id']);

        $error = null;
        $currentCategoryId = (int)$profile['category_id'];
        $profileName = $profile['profile_name'];

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== \Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $categoryId = $req->get('category_id') ? (int)$req->get('category_id') : null;
                $subcategoryId = $req->get('subcategory_id') ? (int)$req->get('subcategory_id') : null;
                $profileName = trim((string)$req->get('profile_name'));

                if ($profileName === '') {
                    $error = _p('hulahoot_profile_name_required');
                } else {
                    try {
                        $service->update($userId, $profileId, $categoryId, $subcategoryId, $profileName);

                        // update() only ever touches hulahoot_profile's own
                        // row (see its docblock) - if the profile just
                        // edited is the one currently active, the Master
                        // Account's displayed identity (full_name, synced
                        // from profile_name) needs re-applying too, or the
                        // header/profile page/etc keep showing the name
                        // from before this edit until the next switch.
                        // Editing an inactive profile only ever updates its
                        // own stored data, exactly as before - this check
                        // is what keeps that true.
                        $oIdentity = new \Apps\Hulahoot\Service\ActiveIdentity();
                        $aActiveProfile = $oIdentity->getActiveProfile($userId);
                        if ($aActiveProfile && (int)$aActiveProfile['profile_id'] === $profileId) {
                            $oIdentity->applyToMasterAccount($userId, $profileId);
                        }

                        return url()->send('/my-profiles', [], _p('hulahoot_profile_updated'));
                    } catch (\InvalidArgumentException $e) {
                        $error = $e->getMessage();
                        $currentCategoryId = $categoryId ?: $currentCategoryId;
                    }
                }
            }
        }

        $categories = [];
        $subcategories = [];
        if ($profileType && $profileType['requires_category']) {
            $categories = $service->getTopCategoriesForType((int)$profileType['profile_type_id']);
            $subcategories = $currentCategoryId ? $service->getSubcategories($currentCategoryId) : [];
        }

        list($categoriesByType, $subcategoriesByCategory) = $service->getCategoryMapsForJs();

        title(_p('hulahoot_edit_profile'));

        return view('edit-profile.html', [
            'profile' => $profile,
            'profile_type' => $profileType,
            'profile_name' => $profileName,
            'categories' => $categories,
            'current_category_id' => $currentCategoryId,
            'current_subcategory_id' => (int)$profile['subcategory_id'],
            'subcategories' => $subcategories,
            'categories_by_type_json' => json_encode($categoriesByType),
            'subcategories_by_category_json' => json_encode($subcategoriesByCategory),
            'error' => $error,
            'csrf_token' => \Phpfox::getService('log.session')->getToken(),
        ]);
    });

});

/**
 * AdminCP: Profile Types / Profile Categories management.
 *
 * Registered as modern Core\Route paths (not a PF.Base/module/hulahoot
 * admincp controller-probe) for the same reason the rest of this App
 * uses Core\Route: it keeps every Hulahoot file under PF.Site/Apps/hulahoot,
 * with no new legacy module to register/install. Core\Route is tried
 * before the legacy segment router for every URL (see docs/ProjectStructure.md
 * §2), so these paths are matched before Admincp_Component_Controller_Index
 * ever loads - and the AdminCP theme switch itself (Phpfox_Template,
 * PF.Base/include/library/phpfox/template/template.class.php) keys purely
 * off the URL's first segment being "admincp", not off which router or
 * controller class handled the request - so these pages render inside the
 * normal adminpanel theme/chrome exactly like a legacy admincp screen would.
 *
 * Permission gate: every route below checks auth()->isAdmin(true) - phpFox's
 * standard coarse AdminCP gate (Phpfox::isUser() + admincp.has_admin_access),
 * the same check Admincp_Component_Controller_Index itself makes first.
 * Deliberately NOT layered with the finer hulahoot.can_manage_profile_types
 * -style per-action user_group_settings docs/AdminCPDesign.md §2 proposes -
 * registering new user_group_settings safely (Core\App\App::$user_group_settings)
 * needs verification against a real install/upgrade run this pass didn't
 * include (backend/CRUD scope only, no UI polish). Any admin with general
 * AdminCP access can use every screen below today; adding the granular
 * per-action permission split is a documented, additive follow-up, not a
 * blocker to this CRUD working correctly.
 *
 * CSRF: same token convention as the /my-profiles routes above
 * (log.session's token, hidden hulahoot_token field, compared on every POST).
 */
// Rendering layer only - mirrors Core Photos' architecture exactly:
// Core\Route is used purely for URL matching, each closure hands off to a
// classic Phpfox_Component controller via dispatch()+'controller', and that
// controller renders through the classic Smarty template pipeline in the
// SAME pass as the AdminCP shell (one document, not two). All business
// logic still lives in Service\ProfileTypeAdmin / Service\ProfileCategoryAdmin
// - completely unchanged. Every URL below is byte-identical to before.
\Phpfox_Module::instance()
    // Required for phpFox to auto-discover Apps\Hulahoot\Service\Callback
    // (Phpfox_Module::_loadCallbacks() only resolves the namespaced class
    // once an alias is registered here - every other first-party app
    // already does this). Purely additive: it only populates two lookup
    // arrays used by callback/alias resolution, nothing existing depends
    // on 'hulahoot' NOT being aliased.
    ->addAliasNames('hulahoot', 'Hulahoot')
    // Required alongside addAliasNames() above: Phpfox_Module::getService()
    // only resolves a service by dotted name ('hulahoot.callback') through
    // this map - class_exists() alone (which addAliasNames enables) is not
    // enough for getService() itself to instantiate it. Confirmed by
    // reading getService()'s resolution order directly. Matches
    // core-pages/core-photos, which register their own '{app}.callback'
    // here the same way.
    ->addServiceNames([
        'hulahoot.callback' => \Apps\Hulahoot\Service\Callback::class,
    ])
    ->addTemplateDirs([
        'hulahoot' => PHPFOX_DIR_SITE_APPS . 'hulahoot' . PHPFOX_DS . 'views',
    ])
    ->addComponentNames('controller', [
        'hulahoot.admincp.profiletype' => \Apps\Hulahoot\Controller\Admin\ProfileTypeController::class,
        'hulahoot.admincp.profiletype-add' => \Apps\Hulahoot\Controller\Admin\ProfileTypeAddController::class,
        'hulahoot.admincp.profiletype-delete' => \Apps\Hulahoot\Controller\Admin\ProfileTypeDeleteController::class,
        'hulahoot.admincp.profilecategory' => \Apps\Hulahoot\Controller\Admin\ProfileCategoryController::class,
        'hulahoot.admincp.profilecategory-add' => \Apps\Hulahoot\Controller\Admin\ProfileCategoryAddController::class,
        'hulahoot.admincp.profilecategory-delete' => \Apps\Hulahoot\Controller\Admin\ProfileCategoryDeleteController::class,

        // Phase 2: companion overlay rules for native Core Subscriptions
        // packages - see Service/SubscriptionPackageAdmin.php. No
        // create/delete controller: packages themselves are still only
        // created/deleted from Core Subscriptions' own AdminCP.
        'hulahoot.admincp.subscriptionpackage' => \Apps\Hulahoot\Controller\Admin\SubscriptionPackageController::class,
        // Named "-add" (not "-edit") to match the naming convention that
        // resolves to views/admincp/subscriptionpackage-form.html - see
        // SubscriptionPackageAddController's own docblock.
        'hulahoot.admincp.subscriptionpackage-add' => \Apps\Hulahoot\Controller\Admin\SubscriptionPackageAddController::class,

        // Phase 2: Industry CRUD - see Service/IndustryAdmin.php and
        // Installation/Database/Industry.php.
        'hulahoot.admincp.industry' => \Apps\Hulahoot\Controller\Admin\IndustryController::class,
        'hulahoot.admincp.industry-add' => \Apps\Hulahoot\Controller\Admin\IndustryAddController::class,
        'hulahoot.admincp.industry-delete' => \Apps\Hulahoot\Controller\Admin\IndustryDeleteController::class,
        // "Click an Industry, see its packages" - see
        // Controller/Admin/IndustryPackagesController.php.
        'hulahoot.admincp.industry-packages' => \Apps\Hulahoot\Controller\Admin\IndustryPackagesController::class,

        // Backs the "SWESS Wallet" profile tab (/username/hulahoot),
        // reached via the standard {module}.profile sub-section dispatch
        // in Profile_Component_Controller_Index. Phase 1: renders real
        // subscription status via Service\Subscription (a thin wrapper
        // over Core Subscriptions) - see Controller/WalletController.php
        // and PHASE_1_SUBSCRIPTION.md. Was ProfileRedirectController in
        // Phase 0 (placeholder redirect only); renamed since it now
        // renders instead of redirecting.
        'hulahoot.profile' => \Apps\Hulahoot\Controller\WalletController::class,
    ])
    ->addComponentNames('ajax', [
        // Drag-and-drop reordering (typeOrdering/categoryOrdering) - the
        // same Core_drag + core.process:updateOrdering mechanism Photos,
        // Pages, and Videos already use for their AdminCP lists.
        'hulahoot.ajax' => \Apps\Hulahoot\Ajax\Ajax::class,
    ]);

group('/admincp/hulahoot', function () {

    route('/', function () {
        auth()->isAdmin(true);

        return url()->send('/admincp/hulahoot/profiletype');
    });

    // GET /admincp/hulahoot/profiletype - browse every Profile Type
    // (active and inactive), with usage counts.
    route('/profiletype', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.profiletype');

        return 'controller';
    });

    // GET/POST /admincp/hulahoot/profiletype/add?id=X - add (no id) or
    // edit (id present), same form for both.
    route('/profiletype/add', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.profiletype-add');

        return 'controller';
    });

    // GET (confirm) / POST (execute) /admincp/hulahoot/profiletype/delete?id=X
    route('/profiletype/delete', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.profiletype-delete');

        return 'controller';
    });

    // GET /admincp/hulahoot/profilecategory?profile_type_id=X - browse
    // categories/subcategories scoped to one type. Renders a type picker
    // instead of a list when profile_type_id is missing.
    route('/profilecategory', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.profilecategory');

        return 'controller';
    });

    // GET/POST /admincp/hulahoot/profilecategory/add?profile_type_id=X
    // (create) or ?id=X (edit) - parent_id optionally pre-filled via
    // ?parent_id=X when reached from a "New Subcategory" row action.
    route('/profilecategory/add', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.profilecategory-add');

        return 'controller';
    });

    // GET (confirm) / POST (execute) /admincp/hulahoot/profilecategory/delete?id=X
    route('/profilecategory/delete', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.profilecategory-delete');

        return 'controller';
    });

    // GET /admincp/hulahoot/subscriptionpackage - every native Core
    // Subscriptions package, with whichever Hulahoot companion rules exist
    // merged in. Phase 2 foundation - see docs/PHASE_2_SUBSCRIPTION.md.
    route('/subscriptionpackage', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.subscriptionpackage');

        return 'controller';
    });

    // GET/POST /admincp/hulahoot/subscriptionpackage/edit?id=X - edit the
    // Hulahoot companion rules + industry links for one native package.
    // Never creates/edits/deletes the native package itself.
    route('/subscriptionpackage/edit', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.subscriptionpackage-add');

        return 'controller';
    });

    // GET /admincp/hulahoot/industry - browse every Industry (active and
    // inactive), with package-link counts.
    route('/industry', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.industry');

        return 'controller';
    });

    // GET/POST /admincp/hulahoot/industry/add?id=X - add (no id) or edit
    // (id present), same form for both.
    route('/industry/add', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.industry-add');

        return 'controller';
    });

    // GET /admincp/hulahoot/industry/packages?id=X - every package
    // assigned to this Industry, plus a quick assign/unassign picker.
    // POST assigns or unassigns one package (see
    // IndustryPackagesController for which field drives which action).
    route('/industry/packages', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.industry-packages');

        return 'controller';
    });

    // GET (confirm) / POST (execute) /admincp/hulahoot/industry/delete?id=X
    route('/industry/delete', function () {
        auth()->isAdmin(true);
        \Phpfox::getLib('module')->dispatch('hulahoot.admincp.industry-delete');

        return 'controller';
    });

});

// Placeholder-only route for the "Create Promotion" button on the profile
// page (see hooks/profile.template_block_pic_info.php). Renders a stub
// "coming soon" page - no promotion logic lives here. Swap the view for
// the real Partnership Composer later; the URL and button that link to
// it don't need to change when that happens.
group('/hulahoot/promotions', function () {

    // Phase 2 adds the entitlement gate ahead of the still-placeholder
    // composer: a promotion can only be started with an active,
    // completed subscription purchase. See Service/Entitlement.php and
    // docs/HULAHOOT_INTEGRATION.md - this is the seam the main Hulahoot
    // application's publishing integration will eventually sit behind
    // (Service/HulahootPublisher.php, not implemented yet on purpose).
    route('/create', function () {
        auth()->membersOnly();

        $entitlementService = new \Apps\Hulahoot\Service\Entitlement();
        $aEntitlement = $entitlementService->getActiveEntitlement(\Phpfox::getUserId());

        title(_p('hulahoot_create_promotion'));

        return view('promotions/create.html', [
            'entitlement' => $aEntitlement,
        ]);
    });

});

/**
 * Phase 2: the post-login marketplace. Find Your Industry (search/browse
 * every active Industry) -> Industry detail (every active package
 * assigned to it, fully dynamic - see Service/Marketplace.php). Purchase
 * itself is never reimplemented here: each package's CTA opens the same
 * native subscribe.upgrade ajax modal Core Subscriptions' own package
 * list uses (see views/block/entry-package.html.php in that app) - phpFox
 * remains fully responsible for billing, gateways, renewal, and user
 * group assignment.
 */
group('/find-your-industry', function () {

    route('/', function () {
        auth()->membersOnly();

        $service = new \Apps\Hulahoot\Service\Marketplace();

        title(_p('hulahoot_find_your_industry'));

        return view('find-your-industry.html', [
            'industries' => $service->getActiveIndustries(),
        ]);
    });

});

group('/industry', function () {

    route('/', function () {
        auth()->membersOnly();

        $service = new \Apps\Hulahoot\Service\Marketplace();
        $sSlug = (string)request()->get('slug');
        $aIndustry = $sSlug ? $service->getActiveIndustryBySlug($sSlug) : false;

        if (!$aIndustry) {
            return url()->send('/find-your-industry', [], _p('hulahoot_industry_not_found'));
        }

        $aPackages = $service->getPackagesForIndustry((int)$aIndustry['industry_id']);

        title(_p($aIndustry['name']));

        return view('industry-detail.html', [
            'industry' => $aIndustry,
            'packages' => $aPackages,
            'csrf_token' => \Phpfox::getService('log.session')->getToken(),
        ]);
    });

    // POST /industry/subscribe - the package card's "Choose Plan"/CTA
    // button. Calls Service/PurchaseFlow.php, which uses the native
    // purchase services directly (see that class's own docblock for
    // exactly why this isn't just the native subscribe.upgrade ajax
    // modal). Free packages land back on the Industry page with a
    // success message; paid packages hand off to the native gateway-
    // selection page (subscribe.register) - phpFox owns everything from
    // that point on.
    route('/subscribe', function () {
        auth()->membersOnly();

        if (request()->method() !== 'POST') {
            return url()->send('/find-your-industry');
        }

        if (request()->get('hulahoot_token') !== \Phpfox::getService('log.session')->getToken()) {
            return url()->send('/find-your-industry', [], _p('hulahoot_invalid_token'));
        }

        $iPackageId = (int)request()->get('package_id');
        $sIndustrySlug = (string)request()->get('industry_slug');
        $service = new \Apps\Hulahoot\Service\PurchaseFlow();

        try {
            $aResult = $service->initiate(\Phpfox::getUserId(), $iPackageId);
        } catch (\InvalidArgumentException $e) {
            return url()->send('/industry', ['slug' => $sIndustrySlug], $e->getMessage());
        }

        if ($aResult['free']) {
            return url()->send('/industry', ['slug' => $sIndustrySlug], _p('hulahoot_subscribed_successfully'));
        }

        return url()->send('subscribe.register', ['id' => $aResult['purchase_id']]);
    });

});

// The Portal is a backend for finding an Industry and buying a package,
// not a social network - the native feed should never be a logged-in
// user's home screen. Overrides the bare "/" for members only.
//
// IMPORTANT (learned by breaking the guest homepage first try): once
// Core\Route::match() finds a registered pattern, that route owns the
// entire response - Core\Route\Controller::get() calls exit on an empty
// return value instead of falling through to the legacy resolver (see
// PF.Src/Core/Route/Controller.php - `if (empty($content) || ...) { exit; }`
// runs unconditionally, no fallback path exists). So this route cannot
// just "do nothing" for guests; it has to explicitly render what the
// legacy resolver would have rendered - the exact same dispatch+'controller'
// pattern already used for every admincp route in this file
// (Phpfox_Module::setController() picks 'core.index-visitor' vs
// 'core.index-member' by the same Phpfox::isUser() check when there's no
// Core\Route match at all; this reproduces the guest half of that by hand).
route('/', function () {
    if (!auth()->isLoggedIn()) {
        \Phpfox::getLib('module')->dispatch('core.index-visitor');

        return 'controller';
    }

    $service = new \Apps\Hulahoot\Service\Marketplace();

    title(_p('hulahoot_find_your_industry'));

    return view('find-your-industry.html', [
        'industries' => $service->getActiveIndustries(),
    ]);
});

// Overrides Core_Subscriptions' own bare /subscribe route (Apps\Core_
// Subscriptions\Controller\IndexController, component 'subscribe.index') -
// the raw, unbranded native "Membership Packages" browse page. Package
// browsing for Hulahoot only ever happens via Industry pages
// (/find-your-industry -> /industry) - a customer landing on the native
// page directly (e.g. after a dead-end during checkout, or a stale
// bookmark) has no way to tell it's even part of Hulahoot. Safe to
// override outright: Core\Route stores routes in a flat array keyed by
// the normalized path (Core/Route.php's add()), and this app's start.php
// loads after core-subscriptions', so this registration simply replaces
// that one key - every other /subscribe/* route (register, complete,
// list, compare, renew-method) has its own distinct key and is
// completely untouched.
route('/subscribe', function () {
    return url()->send('/find-your-industry');
});
