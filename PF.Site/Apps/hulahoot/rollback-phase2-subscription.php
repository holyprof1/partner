<?php
/**
 * Phase 2 Subscription foundation - targeted rollback.
 *
 * Drops ONLY hulahoot_subscription_package and
 * hulahoot_subscription_package_category, via the same native
 * Core\App\Install\Database\Table::drop() primitive
 * Apps\Hulahoot\Install::uninstall() itself calls - reused here directly
 * rather than through uninstall(), because uninstall() also deletes the
 * app's :apps/:module/:block/:component/:cron rows and drops every table
 * in Install::$database (ProfileType, ProfileCategory, Profile included)
 * - far more than this migration touched. Never touches subscribe_* or
 * any other Core Subscriptions/core phpFox table.
 *
 * Usage: php rollback-phase2-subscription.php
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

require_once __DIR__ . '/Installation/Database/SubscriptionPackageCategory.php';
require_once __DIR__ . '/Installation/Database/SubscriptionPackage.php';

$isCli = (php_sapi_name() === 'cli');

// Junction table first (nothing references it), then the companion
// table it points to - tidy drop order even though neither has a hard
// FK constraint (this app's tables never do - see the migration class
// docblocks).
$oCategoryLinks = new \Apps\Hulahoot\Installation\Database\SubscriptionPackageCategory();
$oCategoryLinks->drop();

$oRules = new \Apps\Hulahoot\Installation\Database\SubscriptionPackage();
$oRules->drop();

$message = "Phase 2 Subscription rollback complete: hulahoot_subscription_package and hulahoot_subscription_package_category dropped.\n";
$isCli ? fwrite(STDOUT, $message) : print(htmlspecialchars($message));
