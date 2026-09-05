<?php
/**
 * Publishes every SWESS post whose status is 'scheduled' and whose
 * scheduled_at has arrived - see Service/Swess.php::publishDuePosts() for
 * the actual transition logic (locked per-post, re-checks status before
 * acting, marks 'failed' with a reason instead of getting silently stuck).
 *
 * Intended to run every few minutes via cron. Same reasoning and same
 * direct-CLI-bootstrap pattern as send-expiry-reminders.php: this app has
 * no cron entry point of its own, and phpFox's own native cron table
 * (phpfox_cron / cron.php) has nothing triggering it at the OS level on
 * this domain either (confirmed live - the only crontab entry on this box
 * hits a completely different, unrelated Laravel app at www.hulahoot.com).
 * A dedicated crontab line calling this script directly is the same
 * already-established workaround, not a new pattern.
 *
 * Usage: php publish-scheduled-swess-posts.php
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

$service = new \Apps\Hulahoot\Service\Swess();
$result = $service->publishDuePosts();

$out('SWESS scheduled posts: ' . count($result['published']) . ' published, ' . count($result['failed']) . ' failed.');
