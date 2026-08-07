<?php
/**
 * Replaces the original demo package lineup (Starter Spotlight, Growth
 * Accelerator, Premier Presence, Enterprise Reach, Beauty Essentials,
 * Classroom Connect - each hand-linked to a different, inconsistent
 * subset of Industries) with a single clean 3-tier lineup: Lite, Elite,
 * Dominance. Requested directly: "we should be seeing, elite, lite and
 * dominance.. then we can add more, edit it and so" - a consistent
 * starting point every Industry shows, that can be customized from
 * AdminCP afterward exactly like any hand-created package.
 *
 * The three new packages are explicitly linked to every active Industry
 * (not left "universal" with zero links) - functionally identical on
 * the public storefront either way (Marketplace::getPackagesForIndustry()
 * shows both), but an explicit link is what makes each Industry's own
 * "Manage Packages" AdminCP screen actually list them, rather than
 * showing "No packages are assigned to this Industry yet" - which
 * looked broken when seen live even though nothing was wrong.
 *
 * Reversible: the old packages are deactivated (is_active = 0 on both
 * the native subscribe_package row and its Hulahoot companion row),
 * never deleted - they can be reactivated from AdminCP > Subscription
 * Packages at any time, and every purchase/history row tied to them is
 * untouched.
 *
 * Native packages are created via the real AdminCP HTTP form for the
 * same reason seed-demo-data.php does (see that file's docblock) - run
 * logged in as an admin, same SEED_COOKIE env var.
 *
 * Usage: SEED_COOKIE="..." php restructure-lite-elite-dominance.php
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

$sCookie = getenv('SEED_COOKIE');
if (!$sCookie) {
    $out('SEED_COOKIE not set - aborting.');
    exit(1);
}

$aAllIndustryIds = (array)db()->select('industry_id')
    ->from(':hulahoot_industry')
    ->where(['is_active' => 1])
    ->execute('getSlaveRows');
$aAllIndustryIds = array_map('intval', array_column($aAllIndustryIds, 'industry_id'));

// ---------------------------------------------------------------------
// 1. Deactivate the old lineup (native row + Hulahoot companion row).
//    Deactivating rather than deleting - every historical purchase row
//    stays intact, and any of these can be reactivated from AdminCP at
//    any time.
// ---------------------------------------------------------------------

$aOldTitles = ['Starter Spotlight', 'Growth Accelerator', 'Premier Presence', 'Enterprise Reach', 'Beauty Essentials', 'Classroom Connect'];

$sOldTitlesInList = implode(',', array_map(function ($sTitle) {
    return '"' . addslashes($sTitle) . '"';
}, $aOldTitles));

$aOldPackageIds = (array)db()->select('sp.package_id')
    ->from(':subscribe_package', 'sp')
    ->join(':language_phrase', 'lp', 'lp.var_name = sp.title')
    ->where('lp.text IN (' . $sOldTitlesInList . ')')
    ->execute('getSlaveRows');
$aOldPackageIds = array_map('intval', array_column($aOldPackageIds, 'package_id'));

foreach ($aOldPackageIds as $iOldId) {
    db()->update(':subscribe_package', ['is_active' => 0], 'package_id = ' . $iOldId);
    db()->update(':hulahoot_subscription_package', ['is_active' => 0], 'package_id = ' . $iOldId);
    // Also strips every explicit hulahoot_subscription_package_industry
    // link the old package held - deactivating alone already hides it
    // from the public storefront, but left the per-industry "Manage
    // Packages" screen still listing it (as "Inactive"/"Not Configured")
    // under whichever Industries it used to belong to, which is exactly
    // the clutter this whole restructure was meant to remove. Uses the
    // same delete SubscriptionPackageAdmin::removeIndustryFromPackage()
    // does, just for every industry_id at once instead of one at a time.
    db()->delete(':hulahoot_subscription_package_industry', ['package_id' => $iOldId]);
    $out('Deactivated old package id ' . $iOldId . ' and cleared its industry links');
}

// ---------------------------------------------------------------------
// 2. Create the new lineup (native row via the real AdminCP form - see
//    docblock; same helper shape as seed-demo-data.php).
// ---------------------------------------------------------------------

// is_active = 1 only - a deactivated package (e.g. an old duplicate
// left over from a title-phrase collision, see this file's own git
// history) must never be found by the idempotency check below. Two
// active packages can never legitimately share one title (Core
// Subscriptions' own Add Package form doesn't allow saving a duplicate
// title through the UI), so filtering to active rows is what actually
// makes this check unambiguous - checked once by title text alone,
// two same-named packages (one active, one a deactivated leftover)
// made the check pick whichever happened to sort first, which isn't
// guaranteed to be the real one.
$fGetPackageTitles = function () {
    $aRows = (array)db()->select('sp.package_id, lp.text')
        ->from(':subscribe_package', 'sp')
        ->join(':language_phrase', 'lp', 'lp.var_name = sp.title')
        ->where(['sp.is_active' => 1])
        ->execute('getSlaveRows');

    $aTitles = [];
    foreach ($aRows as $aRow) {
        $aTitles[(int)$aRow['package_id']] = $aRow['text'];
    }

    return $aTitles;
};

$fCreateNativePackage = function ($sTitle, $sDescription, $sCostUsd, $sRecurringCostUsd, $iRecurringPeriod, $bFree) use ($sCookie, $fGetPackageTitles) {
    $aTitlesBefore = $fGetPackageTitles();

    if (in_array($sTitle, $aTitlesBefore, true)) {
        return null;
    }

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
        // See seed-demo-data.php's own docblock for why this is 2
        // (NORMAL_USER_ID) and never 0 - purchasing a business package
        // must never change the buyer's phpFox account group.
        'user_group_id' => '2',
        'fail_user_group' => '2',
        'number_day_notify_before_expiration' => '0',
        'allow_payment_methods' => ['auto' => '1', 'manual' => '2'],
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
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; HulahootRestructure/1.0)',
    ]);
    curl_exec($ch);
    curl_close($ch);

    $aTitlesAfter = $fGetPackageTitles();
    $aNewIds = array_diff(array_keys($aTitlesAfter), array_keys($aTitlesBefore));

    return $aNewIds ? (int)end($aNewIds) : null;
};

// Site palette only (black / #797979 gray) - no accent colors, per the
// standing "change the purple to site color, black and 797979 gray"
// instruction from earlier in this engagement.
$aNewPlans = [
    [
        'title' => 'Lite',
        'description' => 'A clean starting point for businesses ready to promote.',
        'cost' => '29', 'recurring_cost' => '29', 'recurring_period' => 1, 'is_free' => false,
        'subtitle' => 'Perfect for getting started',
        'badge' => '', 'accent_color' => '#797979', 'button_text' => 'Choose Lite',
        'credits' => 100, 'purchase_limit' => 3, 'campaign_limit' => 2, 'posting_per_day' => 2, 'posting_per_month' => 40,
        'features' => ['3 active promotions', '100 monthly credits', 'Standard placement', 'Community support'],
    ],
    [
        'title' => 'Elite',
        'description' => 'For businesses ready to run always-on promotions.',
        'cost' => '99', 'recurring_cost' => '99', 'recurring_period' => 1, 'is_free' => false,
        'subtitle' => 'Most popular for growing brands',
        'badge' => 'Most Popular', 'accent_color' => '#000000', 'button_text' => 'Choose Elite',
        'credits' => 400, 'purchase_limit' => 10, 'campaign_limit' => 6, 'posting_per_day' => 5, 'posting_per_month' => 120,
        'features' => ['10 active promotions', '400 monthly credits', 'Enhanced placement', 'Priority support', 'Basic analytics'],
    ],
    [
        'title' => 'Dominance',
        'description' => 'Maximum visibility for established, high-volume businesses.',
        'cost' => '299', 'recurring_cost' => '299', 'recurring_period' => 1, 'is_free' => false,
        'subtitle' => 'Built for market leaders',
        'badge' => 'Best Value', 'accent_color' => '#000000', 'button_text' => 'Choose Dominance',
        'credits' => 1500, 'purchase_limit' => null, 'campaign_limit' => null, 'posting_per_day' => null, 'posting_per_month' => null,
        'features' => ['Unlimited promotions', '1,500 monthly credits', 'Top-tier placement', 'Dedicated support', 'Full analytics suite', 'Custom branding'],
    ],
];

$packageService = new \Apps\Hulahoot\Service\SubscriptionPackageAdmin();
$iCreatedPackages = 0;

foreach ($aNewPlans as $iOrder => $aPlan) {
    $iPackageId = $fCreateNativePackage(
        $aPlan['title'],
        $aPlan['description'],
        $aPlan['cost'],
        $aPlan['recurring_cost'],
        $aPlan['recurring_period'],
        $aPlan['is_free']
    );

    if ($iPackageId === null) {
        $iPackageId = array_search($aPlan['title'], $fGetPackageTitles(), true);
        $out('Native package already exists: ' . $aPlan['title'] . ' (id ' . $iPackageId . ') - reapplying Hulahoot rules.');
    } else {
        $iCreatedPackages++;
    }

    // Explicitly linked to every Industry (not left universal/unchecked)
    // - shows identically either way on the public storefront, but an
    // explicit link is what makes each Industry's own "Manage Packages"
    // screen actually list Lite/Elite/Dominance instead of showing "No
    // packages are assigned to this Industry yet", which read as broken
    // when seen live even though nothing was actually wrong.
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
        $aAllIndustryIds,
        $aPlan['features']
    );

    $out('Applied Hulahoot rules: ' . $aPlan['title'] . ' (id ' . $iPackageId . ') -> explicitly assigned to ' . count($aAllIndustryIds) . ' Industries');
}

$out('Done. ' . $iCreatedPackages . ' new package(s) created, ' . count($aOldPackageIds) . ' old package(s) deactivated.');
