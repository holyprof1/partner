<?php
/**
 * Splits the shared Lite/Elite/Dominance packages (one native package per
 * tier, explicitly linked to all 10 Industries - see
 * restructure-lite-elite-dominance.php) into one independent native
 * package PER INDUSTRY PER TIER (30 total). Requested directly after
 * seeing that editing "Lite" from Automotive's Manage Packages screen
 * would have changed Lite for every other Industry too, since it was one
 * shared row: "when i go to automotive, manage and edited something in
 * there.. it should only affect that plan on automotive alone.. i can
 * even change name and anything at all."
 *
 * Each new package is titled "{Industry} - {Tier}" (e.g. "Automotive -
 * Lite") so AdminCP's own flat Subscription Packages list - which shows
 * every package across every Industry in one table - stays
 * distinguishable. hulahoot_subscription_package.display_name is set to
 * just the tier name ("Lite") - Marketplace::getPackagesForIndustry()
 * prefers display_name over the native title when present, so customers
 * still see the same clean "Lite"/"Elite"/"Dominance" they did before;
 * only AdminCP shows the industry-qualified name. Requires the
 * display_name column - run install-cli.php (or let a normal deploy run
 * it) before this script.
 *
 * Each of the 30 is linked to exactly one Industry, so editing any one
 * of them - name, price, features, anything - from AdminCP only ever
 * affects that Industry. All 30 start with identical pricing/features
 * per tier (same numbers restructure-lite-elite-dominance.php used) -
 * a shared starting point, independently editable from here on.
 *
 * Reversible: the three old shared packages are deactivated (never
 * deleted) and their industry links cleared, same pattern as every
 * other migration in this app.
 *
 * Native packages are created via the real AdminCP HTTP form for the
 * same reason seed-demo-data.php does (see that file's docblock) - run
 * logged in as an admin, same SEED_COOKIE env var.
 *
 * Usage: SEED_COOKIE="..." php split-packages-per-industry.php
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

if (!Phpfox::getLib('database')->isField(Phpfox::getT('hulahoot_subscription_package'), 'display_name')) {
    $out('hulahoot_subscription_package.display_name does not exist yet - run install-cli.php first.');
    exit(1);
}

// ---------------------------------------------------------------------
// 1. Deactivate the three shared packages (native row + Hulahoot
//    companion row) and clear their industry links - same treatment
//    the original six demo packages got in restructure-lite-elite-
//    dominance.php.
// ---------------------------------------------------------------------

$sOldTitlesInList = implode(',', array_map(function ($sTitle) {
    return '"' . addslashes($sTitle) . '"';
}, ['Lite', 'Elite', 'Dominance']));

$aOldPackageIds = (array)db()->select('sp.package_id')
    ->from(':subscribe_package', 'sp')
    ->join(':language_phrase', 'lp', 'lp.var_name = sp.title')
    ->where('lp.text IN (' . $sOldTitlesInList . ') AND sp.is_active = 1')
    ->execute('getSlaveRows');
$aOldPackageIds = array_map('intval', array_column($aOldPackageIds, 'package_id'));

foreach ($aOldPackageIds as $iOldId) {
    db()->update(':subscribe_package', ['is_active' => 0], 'package_id = ' . $iOldId);
    db()->update(':hulahoot_subscription_package', ['is_active' => 0], 'package_id = ' . $iOldId);
    db()->delete(':hulahoot_subscription_package_industry', ['package_id' => $iOldId]);
    $out('Deactivated shared package id ' . $iOldId . ' and cleared its industry links');
}

// ---------------------------------------------------------------------
// 2. Create 30 independent packages: one per Industry per tier.
// ---------------------------------------------------------------------

$aIndustries = (array)db()->select('industry_id, name')
    ->from(':hulahoot_industry')
    ->where(['is_active' => 1])
    ->order('sort_order ASC, industry_id ASC')
    ->execute('getSlaveRows');

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

$fCreateNativePackage = function ($sTitle, $sDescription, $sCostUsd, $iRecurringPeriod) use ($sCookie, $fGetPackageTitles) {
    $aTitlesBefore = $fGetPackageTitles();

    if (in_array($sTitle, $aTitlesBefore, true)) {
        return null;
    }

    $aVal = [
        'title' => ['en' => $sTitle],
        'description' => ['en' => $sDescription],
        'cost' => ['USD' => $sCostUsd, 'EUR' => $sCostUsd, 'GBP' => $sCostUsd],
        'recurring_cost' => ['USD' => $sCostUsd, 'EUR' => $sCostUsd, 'GBP' => $sCostUsd],
        'recurring_period' => (string)$iRecurringPeriod,
        'is_free' => '0',
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
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; HulahootSplit/1.0)',
    ]);
    curl_exec($ch);
    curl_close($ch);

    $aTitlesAfter = $fGetPackageTitles();
    $aNewIds = array_diff(array_keys($aTitlesAfter), array_keys($aTitlesBefore));

    return $aNewIds ? (int)end($aNewIds) : null;
};

// Same numbers every Industry starts with - independently editable
// from AdminCP afterward. Site palette only (black / #797979 gray), no
// accent colors, per the standing color instruction from earlier in
// this engagement.
$aTiers = [
    [
        'name' => 'Lite',
        'description' => 'A clean starting point for businesses ready to promote.',
        'cost' => '29', 'recurring_period' => 1,
        'subtitle' => 'Perfect for getting started',
        'badge' => '', 'accent_color' => '#797979', 'button_text' => 'Choose Lite',
        'credits' => 100, 'purchase_limit' => 3, 'campaign_limit' => 2, 'posting_per_day' => 2, 'posting_per_month' => 40,
        'features' => ['3 active promotions', '100 monthly credits', 'Standard placement', 'Community support'],
    ],
    [
        'name' => 'Elite',
        'description' => 'For businesses ready to run always-on promotions.',
        'cost' => '99', 'recurring_period' => 1,
        'subtitle' => 'Most popular for growing brands',
        'badge' => 'Most Popular', 'accent_color' => '#000000', 'button_text' => 'Choose Elite',
        'credits' => 400, 'purchase_limit' => 10, 'campaign_limit' => 6, 'posting_per_day' => 5, 'posting_per_month' => 120,
        'features' => ['10 active promotions', '400 monthly credits', 'Enhanced placement', 'Priority support', 'Basic analytics'],
    ],
    [
        'name' => 'Dominance',
        'description' => 'Maximum visibility for established, high-volume businesses.',
        'cost' => '299', 'recurring_period' => 1,
        'subtitle' => 'Built for market leaders',
        'badge' => 'Best Value', 'accent_color' => '#000000', 'button_text' => 'Choose Dominance',
        'credits' => 1500, 'purchase_limit' => null, 'campaign_limit' => null, 'posting_per_day' => null, 'posting_per_month' => null,
        'features' => ['Unlimited promotions', '1,500 monthly credits', 'Top-tier placement', 'Dedicated support', 'Full analytics suite', 'Custom branding'],
    ],
];

$packageService = new \Apps\Hulahoot\Service\SubscriptionPackageAdmin();
$iCreatedPackages = 0;

foreach ($aIndustries as $aIndustry) {
    $sIndustryName = _p($aIndustry['name']);
    $iIndustryId = (int)$aIndustry['industry_id'];

    foreach ($aTiers as $iOrder => $aTier) {
        $sNativeTitle = $sIndustryName . ' - ' . $aTier['name'];

        $iPackageId = $fCreateNativePackage(
            $sNativeTitle,
            $aTier['description'],
            $aTier['cost'],
            $aTier['recurring_period']
        );

        if ($iPackageId === null) {
            $iPackageId = array_search($sNativeTitle, $fGetPackageTitles(), true);
            $out('Native package already exists: ' . $sNativeTitle . ' (id ' . $iPackageId . ') - reapplying Hulahoot rules.');
        } else {
            $iCreatedPackages++;
        }

        $packageService->saveRules(
            $iPackageId,
            [
                'display_name' => $aTier['name'],
                'subtitle' => $aTier['subtitle'],
                'description' => $aTier['description'],
                'badge_text' => $aTier['badge'],
                'accent_color' => $aTier['accent_color'],
                'button_text' => $aTier['button_text'],
                'ordering' => $iOrder + 1,
                'purchase_limit' => $aTier['purchase_limit'],
                'campaign_limit' => $aTier['campaign_limit'],
                'posting_limit_per_day' => $aTier['posting_per_day'],
                'posting_limit_per_month' => $aTier['posting_per_month'],
                'monthly_credits' => $aTier['credits'],
                'is_active' => 1,
            ],
            [$iIndustryId],
            $aTier['features']
        );

        $out('Applied Hulahoot rules: ' . $sNativeTitle . ' (id ' . $iPackageId . ') -> ' . $sIndustryName . ' only, displays as "' . $aTier['name'] . '"');
    }
}

$out('Done. ' . $iCreatedPackages . ' new package(s) created, ' . count($aOldPackageIds) . ' shared package(s) deactivated.');
