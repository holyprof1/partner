<?php
/**
 * Sends renewal reminder emails for every completed purchase currently
 * inside its pre-expiry reminder window OR its post-expiry grace window -
 * see Service/ExpiryReminders.php for the full cadence/cap logic, all of
 * it admin-configurable from AdminCP -> Settings -> Hulahoot Profiles.
 *
 * Intended to run once a day via cron - this app has no cron entry point
 * of its own yet (the main hulahoot.com site's crontab points at its own,
 * unrelated cron.php - confirmed this Partnership Portal install has no
 * scheduled job at all before this one), so this is a direct CLI
 * bootstrap, same pattern as install-cli.php/sync-phrases.php.
 *
 * Usage: php send-expiry-reminders.php
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

$service = new \Apps\Hulahoot\Service\ExpiryReminders();
$iSent = $service->sendDueReminders();

$out('Expiry reminders: ' . $iSent . ' email(s) sent.');
