<?php
/**
 * Seeds the three starting Default Packages - Lite, Elite, Dominance -
 * with the same values split-packages-per-industry.php used to create
 * every Industry's own copy of them. Idempotent by name: skips any
 * template whose name already exists.
 *
 * Unlike the package-creation scripts, this never touches
 * subscribe_package/language_phrase at all - a template is never a real
 * native package - so it needs no admin cookie/session, just a plain
 * CLI run.
 *
 * Usage: php seed-package-templates.php
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

$aTemplates = [
    [
        'name' => 'Lite',
        'description' => 'A clean starting point for businesses ready to promote.',
        'default_cost' => 29, 'recurring_period' => 1,
        'subtitle' => 'Perfect for getting started',
        'badge_text' => '', 'accent_color' => '#797979', 'button_text' => 'Choose Lite',
        'monthly_credits' => 100, 'purchase_limit' => 3, 'campaign_limit' => 2, 'posting_limit_per_day' => 2, 'posting_limit_per_month' => 40,
        'features_text' => "3 active promotions\n100 monthly credits\nStandard placement\nCommunity support",
    ],
    [
        'name' => 'Elite',
        'description' => 'For businesses ready to run always-on promotions.',
        'default_cost' => 99, 'recurring_period' => 1,
        'subtitle' => 'Most popular for growing brands',
        'badge_text' => 'Most Popular', 'accent_color' => '#000000', 'button_text' => 'Choose Elite',
        'monthly_credits' => 400, 'purchase_limit' => 10, 'campaign_limit' => 6, 'posting_limit_per_day' => 5, 'posting_limit_per_month' => 120,
        'features_text' => "10 active promotions\n400 monthly credits\nEnhanced placement\nPriority support\nBasic analytics",
    ],
    [
        'name' => 'Dominance',
        'description' => 'Maximum visibility for established, high-volume businesses.',
        'default_cost' => 299, 'recurring_period' => 1,
        'subtitle' => 'Built for market leaders',
        'badge_text' => 'Best Value', 'accent_color' => '#000000', 'button_text' => 'Choose Dominance',
        'monthly_credits' => 1500, 'purchase_limit' => null, 'campaign_limit' => null, 'posting_limit_per_day' => null, 'posting_limit_per_month' => null,
        'features_text' => "Unlimited promotions\n1,500 monthly credits\nTop-tier placement\nDedicated support\nFull analytics suite\nCustom branding",
    ],
];

$service = new \Apps\Hulahoot\Service\PackageTemplateAdmin();
$aExistingNames = array_column($service->listAll(), 'name');
$iCreated = 0;

foreach ($aTemplates as $iOrder => $aTemplate) {
    if (in_array($aTemplate['name'], $aExistingNames, true)) {
        $out('Template already exists, skipping: ' . $aTemplate['name']);
        continue;
    }

    $aTemplate['ordering'] = $iOrder + 1;
    $aTemplate['is_active'] = 1;

    $iId = $service->create($aTemplate);
    $out('Created template: ' . $aTemplate['name'] . ' (id ' . $iId . ')');
    $iCreated++;
}

$out('Done. ' . $iCreated . ' template(s) created.');
