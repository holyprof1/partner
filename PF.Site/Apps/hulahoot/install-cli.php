<?php
/**
 * Hulahoot database foundation - install/upgrade runner.
 *
 * Runs Apps\Hulahoot\Install::processInstall(), which creates/upgrades
 * hulahoot_profile_type, hulahoot_profile_category, and hulahoot_profile
 * (Core\App\Install\Database\Table::install() is idempotent - safe to run
 * on every deploy, not just the first one), then seeds the three default
 * Profile Types via installer.php.
 *
 * This exists because there is no AdminCP "install this app" step in
 * Sprint 2A's scope (AdminCP is explicitly out of scope), and follows the
 * same pattern as the repository's own cron.php: a direct, non-web
 * bootstrap of PF.Base/start.php with PHPFOX_NO_RUN defined, intended to
 * be run from the command line - `php install-cli.php` - the same way on
 * local, development, and production (see docs/MigrationPlan.md).
 *
 * On the very first run, Apps\Hulahoot\* is not registered in the :apps
 * table yet, so phpFox's dynamic per-app PSR-4 autoloading (which only
 * knows about already-registered apps) can't find these classes yet -
 * they're require_once'd explicitly below for that reason. From the
 * second run onward phpFox's own autoloader also knows how to find them
 * (the app is active by then), so require_once (not require) is
 * required here - a plain require would fatal with "class already in
 * use" once the autoloader and this script both try to load the same
 * class.
 */

define('PHPFOX', true);
define('PHPFOX_DS', DIRECTORY_SEPARATOR);
define('PHPFOX_DIR', __DIR__ . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . 'PF.Base' . PHPFOX_DS);
define('PHPFOX_PARENT_DIR', __DIR__ . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS);
define('PHPFOX_NO_SESSION', true);
define('PHPFOX_NO_USER_SESSION', true);
define('PHPFOX_NO_RUN', true);

require PHPFOX_DIR . 'start.php';

require_once __DIR__ . '/Installation/Database/ProfileType.php';
require_once __DIR__ . '/Installation/Database/ProfileCategory.php';
require_once __DIR__ . '/Installation/Database/Profile.php';
require_once __DIR__ . '/Installation/Seed/ProfileTypeSeeder.php';
require_once __DIR__ . '/Installation/Seed/CategorySeeder.php';
require_once __DIR__ . '/Installation/Seed/UserBackfillSeeder.php';
require_once __DIR__ . '/Service/Profile.php';
require_once __DIR__ . '/Install.php';

$isCli = (php_sapi_name() === 'cli');

$install = new \Apps\Hulahoot\Install();

if (!$install->isValid()) {
    $message = "Hulahoot app is not valid:\n" . implode("\n", $install->getErrorMessages()) . "\n";
    $isCli ? fwrite(STDERR, $message) : print(nl2br(htmlspecialchars($message)));
    exit(1);
}

$result = $install->processInstall();

// The multi-profile user experience (My Profiles menu/tab, account-dropdown
// link, /my-profiles/* routes) was removed from the UI - see
// docs/ProfileOwnership.md - while deliberately leaving every underlying
// service, table, and migration in place for possible future use.
// Install::$menu (see Install.php) still declaratively registers this
// phpfox_menu row via the core App-install machinery, which always inserts
// new menu rows as is_active = 1 (Admincp_Service_Menu_Process::add() has
// no "install inactive" option) - so a fresh install, or any future
// re-run of this script on an environment that never had the row, would
// otherwise silently re-enable this navigation entry. This forces it back
// off every time, idempotently, without touching that core machinery or
// removing the declarative registration itself.
db()->update(':menu', ['is_active' => 0], ['var_name' => 'hulahoot_my_profiles']);

$message = $result !== false
    ? "Hulahoot database foundation installed/upgraded successfully.\n"
    : "Hulahoot install/upgrade did not run (processInstall() returned false).\n";

$isCli ? fwrite(STDOUT, $message) : print(htmlspecialchars($message));

exit($result !== false ? 0 : 1);
