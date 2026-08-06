<?php
/**
 * Phase 2 Industry & Subscription System - targeted rollback.
 *
 * Drops ONLY the tables this milestone added/replaced:
 *   - hulahoot_subscription_package_feature
 *   - hulahoot_subscription_package_industry
 *   - hulahoot_subscription_package
 *   - hulahoot_industry
 *
 * via the same native Core\App\Install\Database\Table::drop() primitive
 * Apps\Hulahoot\Install::uninstall() itself calls - reused here directly
 * rather than through uninstall(), because uninstall() also deletes the
 * app's :apps/:module/:block/:component/:cron rows and drops every table
 * in Install::$database (ProfileType, ProfileCategory, Profile included)
 * - far more than this migration touched. Never touches subscribe_* or
 * any other Core Subscriptions/core phpFox table.
 *
 * Replaces the earlier rollback-phase2-subscription.php (retired - it
 * referenced the now-deleted SubscriptionPackageCategory class).
 *
 * Usage: php rollback-phase2-industry-package.php
 *
 * Safe to run even if the tables don't exist (dropTable is a plain DROP
 * TABLE - confirm current state first if that matters to you; this
 * script does not check existence before dropping).
 */

define('PHPFOX', true);
define('PHPFOX_DS', DIRECTORY_SEPARATOR);
define('PHPFOX_DIR', __DIR__ . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . 'PF.Base' . PHPFOX_DS);
define('PHPFOX_PARENT_DIR', __DIR__ . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS);
define('PHPFOX_NO_SESSION', true);
define('PHPFOX_NO_USER_SESSION', true);
define('PHPFOX_NO_RUN', true);

require PHPFOX_DIR . 'start.php';

require_once __DIR__ . '/Installation/Database/SubscriptionPackageFeature.php';
require_once __DIR__ . '/Installation/Database/SubscriptionPackageIndustry.php';
require_once __DIR__ . '/Installation/Database/SubscriptionPackage.php';
require_once __DIR__ . '/Installation/Database/Industry.php';

$isCli = (php_sapi_name() === 'cli');

// Children/junctions first (nothing references them), then the tables
// they point to - tidy drop order even though nothing here has a hard FK
// constraint (this app's tables never do - see the migration class
// docblocks).
(new \Apps\Hulahoot\Installation\Database\SubscriptionPackageFeature())->drop();
(new \Apps\Hulahoot\Installation\Database\SubscriptionPackageIndustry())->drop();
(new \Apps\Hulahoot\Installation\Database\SubscriptionPackage())->drop();
(new \Apps\Hulahoot\Installation\Database\Industry())->drop();

$message = "Phase 2 Industry & Subscription rollback complete: hulahoot_subscription_package_feature, "
    . "hulahoot_subscription_package_industry, hulahoot_subscription_package, and hulahoot_industry dropped.\n";
$isCli ? fwrite(STDOUT, $message) : print(htmlspecialchars($message));
