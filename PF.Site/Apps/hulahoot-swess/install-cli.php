<?php
/**
 * Registers the SWESS app shell - see Install.php's own docblock for
 * exactly what this does and doesn't do. Same direct-bootstrap pattern
 * as PF.Site/Apps/hulahoot/install-cli.php: `php install-cli.php` from
 * this directory.
 *
 * No database tables, seeders, or controllers are touched by this
 * script - it only inserts/updates one :apps row and the admincp_menu
 * that row exposes. Safe to run repeatedly (Core\App\App::processInstall()
 * is upgrade-idempotent, same as the main Hulahoot app's install-cli.php).
 */

define('PHPFOX', true);
define('PHPFOX_DS', DIRECTORY_SEPARATOR);
define('PHPFOX_DIR', __DIR__ . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . 'PF.Base' . PHPFOX_DS);
define('PHPFOX_PARENT_DIR', __DIR__ . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS . '..' . PHPFOX_DS);
define('PHPFOX_NO_SESSION', true);
define('PHPFOX_NO_USER_SESSION', true);
define('PHPFOX_NO_RUN', true);

require PHPFOX_DIR . 'start.php';

require_once __DIR__ . '/Install.php';

$isCli = (php_sapi_name() === 'cli');

$install = new \Apps\HulahootSwess\Install();

if (!$install->isValid()) {
    $message = "HulahootSwess app is not valid:\n" . implode("\n", $install->getErrorMessages()) . "\n";
    $isCli ? fwrite(STDERR, $message) : print(nl2br(htmlspecialchars($message)));
    exit(1);
}

$result = $install->processInstall();

$message = $result !== false
    ? "SWESS app registered/upgraded successfully.\n"
    : "SWESS app install/upgrade did not run (processInstall() returned false).\n";

$isCli ? fwrite(STDOUT, $message) : print(htmlspecialchars($message));

exit($result !== false ? 0 : 1);
