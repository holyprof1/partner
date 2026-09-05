<?php
/**
 * Seeds realistic demo Industries and Packages so the Phase 2 flow can be
 * evaluated end-to-end instead of against one lonely free package.
 *
 * Every row this creates is completely ordinary, admin-editable data -
 * inserted through the exact same Service\IndustryAdmin /
 * Service\SubscriptionPackageAdmin methods the AdminCP screens call, not
 * a special "seed mode". Deleting or editing any of it later works
 * exactly like deleting/editing anything an admin created by hand.
 *
 * Idempotent by name/title: re-running this script skips any Industry
 * whose name already exists and any native package whose title already
 * exists, so it's safe to run more than once (e.g. after a rollback) -
 * it only ever adds what's missing.
 *
 * Native packages are created via the same HTTP form Core Subscriptions'
 * own AdminCP uses (Apps\Core_Subscriptions\Service\Process::add()) is
 * intentionally NOT called directly here - that method's phrase-creation
 * step is entangled with the AdminCP controller's own request handling in
 * ways not worth reverse-engineering for a seed script when the native
 * HTTP endpoint already does it correctly. Run this script logged in as
 * an admin who has already passed the AdminCP's own login gate
 * (see docs/PHASE_2_SUBSCRIPTION.md) - pass that session's cookies via
 * the SEED_COOKIE env var (same format as a curl -b argument, e.g.
 * "PHPSESSID=...; core8b42user_id=...; core8b42user_hash=...").
 *
 * Usage: SEED_COOKIE="..." php seed-demo-data.php
 */

define('PHPFOX', true);
define('PHPFOX_DS', DIRECTORY_SEPARATOR);
define('PHPFOX_DIR', __DIR__ . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . 'PF.Base' . PHPFOX_DS);
define('PHPFOX_PARENT_DIR', __DIR__ . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS);
define('PHPFOX_NO_SESSION', true);
define('PHPFOX_NO_USER_SESSION', true);
define('PHPFOX_NO_RUN', true);

require PHPFOX_DIR . 'start.php';

$isCli = (php_sapi_name() === 'cli');
$out = function ($sMessage) use ($isCli) {
    $isCli ? fwrite(STDOUT, $sMessage . "\n") : print(htmlspecialchars($sMessage) . "<br>\n");
};

// ---------------------------------------------------------------------
// 1. Industries
// ---------------------------------------------------------------------

$industryService = new \Apps\Hulahoot\Service\IndustryAdmin();

$aIndustries = [
    ['name' => 'Automotive', 'icon' => 'fa-car', 'description' => 'Dealerships, service centers, and auto parts retailers.'],
    ['name' => 'Restaurants', 'icon' => 'fa-cutlery', 'description' => 'Restaurants, cafes, and food service businesses.'],
    ['name' => 'Real Estate', 'icon' => 'fa-home', 'description' => 'Agents, brokerages, and property management companies.'],
    ['name' => 'Beauty', 'icon' => 'fa-scissors', 'description' => 'Salons, spas, and personal care businesses.'],
    ['name' => 'Healthcare', 'icon' => 'fa-heartbeat', 'description' => 'Clinics, dental practices, and wellness providers.'],
    ['name' => 'Retail', 'icon' => 'fa-shopping-bag', 'description' => 'Shops and ecommerce brands of every size.'],
    ['name' => 'Construction', 'icon' => 'fa-wrench', 'description' => 'Contractors, builders, and trade professionals.'],
    ['name' => 'Education', 'icon' => 'fa-graduation-cap', 'description' => 'Schools, tutors, and training providers.'],
    ['name' => 'Fitness', 'icon' => 'fa-heart', 'description' => 'Gyms, studios, and personal trainers.'],
    ['name' => 'Technology', 'icon' => 'fa-laptop', 'description' => 'Software, IT services, and tech startups.'],
];

$aIndustryIdByName = [];
foreach ($aIndustries as $iOrder => $aIndustry) {
    $aExisting = db()->select('industry_id')->from(':hulahoot_industry')->where(['name' => $aIndustry['name']])->execute('getSlaveRow');

    if ($aExisting) {
        $aIndustryIdByName[$aIndustry['name']] = (int)$aExisting['industry_id'];
        $out('Industry already exists, skipping: ' . $aIndustry['name']);
        continue;
    }

    $iId = $industryService->create([
        'name' => $aIndustry['name'],
        'description' => $aIndustry['description'],
        'icon' => $aIndustry['icon'],
        'sort_order' => $iOrder + 1,
        'is_active' => 1,
    ]);
    $aIndustryIdByName[$aIndustry['name']] = $iId;
    $out('Created Industry: ' . $aIndustry['name'] . ' (id ' . $iId . ')');
}

// ---------------------------------------------------------------------
// 2. Native packages (via the real AdminCP HTTP form - see docblock)
// ---------------------------------------------------------------------

$sCookie = getenv('SEED_COOKIE');
if (!$sCookie) {
    $out('SEED_COOKIE not set - skipping package creation (Industries only). Set SEED_COOKIE and re-run to seed packages.');
    exit(0);
}

$sToken = Phpfox::getService('log.session')->getToken();

/**
 * package_id => title text, for every native package - fetched fresh
 * before each create() call (not cached across calls) since re-using
 * db()'s fluent query builder across repeated select/join/where chains
 * inside a closure was observed to leak state between calls (the 2nd+
 * call would silently match an unrelated earlier row) - fetching a
 * plain snapshot array and checking with in_array() sidesteps that
 * entirely, and doubles as how we find the newly-created row's id
 * afterward (whichever package_id is present after but not before).
 *
 * @return array package_id => title text
 */
$fGetPackageTitles = function () {
    $aRows = (array)db()->select('sp.package_id, lp.text')
        ->from(':subscribe_package', 'sp')
        ->join(':language_phrase', 'lp', 'lp.var_name = sp.title')
        ->execute('getSlaveRows');

    $aTitles = [];
    foreach ($aRows as $aRow) {
        $aTitles[(int)$aRow['package_id']] = $aRow['text'];
    }

    return $aTitles;
};

/**
 * @return int|null the new package_id, or null if the title already exists
 */
$fCreateNativePackage = function ($sTitle, $sDescription, $sCostUsd, $sRecurringCostUsd, $iRecurringPeriod, $bFree) use ($sCookie, $fGetPackageTitles) {
    $aTitlesBefore = $fGetPackageTitles();

    if (in_array($sTitle, $aTitlesBefore, true)) {
        return null;
    }

    // A genuinely nested array here (not flat 'val[x][y]' string keys)
    // so http_build_query() emits correct repeated-key bracket notation
    // for visible_group - a flat string key can't produce the same
    // literal key twice, which submitting several checkboxes needs.
    // Native validation ("You must input all prices for 'Price' to
    // create a valid package") rejects a non-free package whose EUR/GBP
    // cost is 0 even though USD is set - mirror the USD amount into
    // every currency for paid packages; free packages (checked via
    // is_free below) are unaffected by this check.
    $sCostAllCurrencies = $bFree ? '0' : $sCostUsd;
    $sRecurringCostAllCurrencies = $bFree ? '0' : $sRecurringCostUsd;

    $aVal = [
        'title' => ['en' => $sTitle],
        'description' => ['en' => $sDescription],
        'cost' => ['USD' => $sCostUsd, 'EUR' => $sCostAllCurrencies, 'GBP' => $sCostAllCurrencies],
        'recurring_cost' => ['USD' => $sRecurringCostUsd, 'EUR' => $sRecurringCostAllCurrencies, 'GBP' => $sRecurringCostAllCurrencies],
        'recurring_period' => (string)$iRecurringPeriod,
        'is_free' => $bFree ? '1' : '0',
        'is_active' => '1',
        'is_registration' => '0',
        'show_price' => '1',
        // NORMAL_USER_ID (Registered Member) - deliberately NOT 0. These
        // are business promotion plans, not phpFox membership upgrades:
        // purchasing/expiring one must never change the buyer's account
        // group. 0 is not a native "no-op" sentinel - Purchase\Process::update()
        // unconditionally calls user.process:updateUserGroup() with
        // whatever's here, so 0 actually sets the group to 0 (invalid).
        // 2 is a real no-op for any ordinary registered customer (their
        // existing group), which is the only case that matters in
        // production; it is NOT a no-op for an already-elevated account
        // (e.g. admin) completing a purchase - never complete a real
        // purchase against an admin/staff account for that reason (this
        // was learned the expensive way - see git history).
        'user_group_id' => '2',
        'fail_user_group' => '2',
        'number_day_notify_before_expiration' => '0',
        'allow_payment_methods' => ['auto' => '1', 'manual' => '2'],
        // Required - a real bug in Core Subscriptions' own
        // admincp/add.html.php (compiled around line 252) does
        // in_array($groupId, $aForms['visible_group']) unconditionally
        // once $aForms is non-empty (i.e. on every POST), which is a hard
        // TypeError if this key is entirely absent from the request (not
        // merely empty) - the browser form only avoids it because every
        // checkbox defaults to checked, so a real admin submission always
        // includes at least one. 1-6 are this install's native user
        // group ids (fetched from the same form); submitting all of them
        // makes the package visible to every group, matching the
        // checked-by-default UI state a human would see.
        'visible_group' => ['1', '2', '3', '4', '5', '6'],
    ];

    $sPostData = http_build_query(['val' => $aVal]);

    $ch = curl_init('https://partnershipportal.hulahoot.com/admincp/subscribe/add/');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $sPostData,
        CURLOPT_COOKIE => $sCookie,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; HulahootSeed/1.0)',
    ]);
    curl_exec($ch);
    curl_close($ch);

    $aTitlesAfter = $fGetPackageTitles();
    $aNewIds = array_diff(array_keys($aTitlesAfter), array_keys($aTitlesBefore));

    return $aNewIds ? (int)end($aNewIds) : null;
};

// name, description, cost, recurring_cost, recurring_period (0=none,1=monthly,2=quarterly,3=biannual,4=yearly), is_free,
// subtitle, badge, accent_color, button_text, monthly_credits, purchase_limit, campaign_limit, posting_limit_per_day, posting_limit_per_month,
// features[], industries[]
$aPackagePlans = [
    [
        'title' => 'Starter Spotlight',
        'description' => 'A low-cost way to test the waters with your first promotion.',
        'cost' => '0', 'recurring_cost' => '0', 'recurring_period' => 0, 'is_free' => true,
        'subtitle' => 'Perfect for getting started',
        'badge' => '', 'accent_color' => '#64748B', 'button_text' => 'Start Free',
        'credits' => 25, 'purchase_limit' => 1, 'campaign_limit' => 1, 'posting_per_day' => 1, 'posting_per_month' => 10,
        'features' => ['1 active promotion', '25 monthly credits', 'Standard placement', 'Community support'],
        'industries' => ['Automotive', 'Restaurants', 'Real Estate', 'Beauty', 'Healthcare', 'Retail', 'Construction', 'Education', 'Fitness', 'Technology'],
    ],
    [
        'title' => 'Growth Accelerator',
        'description' => 'For businesses ready to run always-on promotions.',
        'cost' => '49', 'recurring_cost' => '49', 'recurring_period' => 1, 'is_free' => false,
        'subtitle' => 'Most popular for growing brands',
        'badge' => 'Most Popular', 'accent_color' => '#000000', 'button_text' => 'Choose Growth',
        'credits' => 150, 'purchase_limit' => 5, 'campaign_limit' => 3, 'posting_per_day' => 3, 'posting_per_month' => 60,
        'features' => ['5 active promotions', '150 monthly credits', 'Enhanced placement', 'Priority support', 'Basic analytics'],
        'industries' => ['Automotive', 'Restaurants', 'Real Estate', 'Retail', 'Fitness', 'Technology'],
    ],
    [
        'title' => 'Premier Presence',
        'description' => 'Maximum visibility for established, high-volume businesses.',
        'cost' => '149', 'recurring_cost' => '149', 'recurring_period' => 1, 'is_free' => false,
        'subtitle' => 'Built for market leaders',
        'badge' => '', 'accent_color' => '#0EA5E9', 'button_text' => 'Choose Premier',
        'credits' => 500, 'purchase_limit' => 15, 'campaign_limit' => 10, 'posting_per_day' => 10, 'posting_per_month' => 200,
        'features' => ['15 active promotions', '500 monthly credits', 'Top-tier placement', 'Dedicated support', 'Full analytics suite', 'Custom branding'],
        'industries' => ['Automotive', 'Real Estate', 'Healthcare', 'Construction', 'Technology'],
    ],
    [
        'title' => 'Enterprise Reach',
        'description' => 'Unlimited scale for multi-location and franchise operations.',
        'cost' => '499', 'recurring_cost' => '499', 'recurring_period' => 4, 'is_free' => false,
        'subtitle' => 'For multi-location operators',
        'badge' => 'Best Value', 'accent_color' => '#DC2626', 'button_text' => 'Talk to Sales',
        'credits' => 2000, 'purchase_limit' => null, 'campaign_limit' => null, 'posting_per_day' => null, 'posting_per_month' => null,
        'features' => ['Unlimited promotions', '2,000 monthly credits', 'Maximum brand exposure', 'Dedicated account manager', 'Custom campaign collaboration', 'Early access to new features'],
        'industries' => ['Retail', 'Healthcare', 'Construction', 'Education'],
    ],
    [
        'title' => 'Beauty Essentials',
        'description' => 'A plan tailored to salons, spas, and beauty professionals.',
        'cost' => '29', 'recurring_cost' => '29', 'recurring_period' => 1, 'is_free' => false,
        'subtitle' => 'Made for salons and spas',
        'badge' => '', 'accent_color' => '#EC4899', 'button_text' => 'Choose Plan',
        'credits' => 100, 'purchase_limit' => 3, 'campaign_limit' => 2, 'posting_per_day' => 2, 'posting_per_month' => 40,
        'features' => ['3 active promotions', '100 monthly credits', 'Enhanced placement', 'Priority support'],
        'industries' => ['Beauty'],
    ],
    [
        'title' => 'Classroom Connect',
        'description' => 'Affordable promotion for schools and education providers.',
        'cost' => '19', 'recurring_cost' => '19', 'recurring_period' => 1, 'is_free' => false,
        'subtitle' => 'Built for schools and tutors',
        'badge' => '', 'accent_color' => '#F59E0B', 'button_text' => 'Choose Plan',
        'credits' => 75, 'purchase_limit' => 2, 'campaign_limit' => 2, 'posting_per_day' => 2, 'posting_per_month' => 30,
        'features' => ['2 active promotions', '75 monthly credits', 'Standard placement', 'Community support'],
        'industries' => ['Education'],
    ],
];

$packageService = new \Apps\Hulahoot\Service\SubscriptionPackageAdmin();
$iCreatedPackages = 0;

foreach ($aPackagePlans as $iOrder => $aPlan) {
    $iPackageId = $fCreateNativePackage(
        $aPlan['title'],
        $aPlan['description'],
        $aPlan['cost'],
        $aPlan['recurring_cost'],
        $aPlan['recurring_period'],
        $aPlan['is_free']
    );

    if ($iPackageId === null) {
        // Native package already existed (from an earlier partial run,
        // or created by hand) - still (re)apply the Hulahoot companion
        // rules/features/industry links below rather than skipping
        // entirely, so a partially-seeded package (native row created,
        // companion row never written because an earlier run errored
        // partway through) gets completed on re-run instead of staying
        // half-done forever.
        $iPackageId = array_search($aPlan['title'], $fGetPackageTitles(), true);
        $out('Native package already exists: ' . $aPlan['title'] . ' (id ' . $iPackageId . ') - reapplying Hulahoot rules.');
    } else {
        $iCreatedPackages++;
    }

    $aIndustryIds = [];
    foreach ($aPlan['industries'] as $sIndustryName) {
        if (isset($aIndustryIdByName[$sIndustryName])) {
            $aIndustryIds[] = $aIndustryIdByName[$sIndustryName];
        }
    }

    $packageService->saveRules(
        $iPackageId,
        [
            'subtitle' => $aPlan['subtitle'],
            'description' => $aPlan['description'],
            'badge_text' => $aPlan['badge'],
            'accent_color' => $aPlan['accent_color'],
            'button_text' => $aPlan['button_text'],
            'ordering' => $iOrder + 1,
            'purchase_limit' => $aPlan['purchase_limit'],
            'campaign_limit' => $aPlan['campaign_limit'],
            'posting_limit_per_day' => $aPlan['posting_per_day'],
            'posting_limit_per_month' => $aPlan['posting_per_month'],
            'monthly_credits' => $aPlan['credits'],
            'is_active' => 1,
        ],
        $aIndustryIds,
        $aPlan['features']
    );

    $out('Applied Hulahoot rules: ' . $aPlan['title'] . ' (id ' . $iPackageId . ') -> ' . implode(', ', $aPlan['industries']));
}

$out('Done. ' . $iCreatedPackages . ' new package(s) created.');
