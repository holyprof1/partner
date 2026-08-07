<?php
/**
 * One-off: point phpFox's native post-login/post-signup redirect at the
 * Phase 2 "Find Your Industry" marketplace instead of the default feed.
 *
 * Sets the native user.redirect_after_login / user.redirect_after_signup
 * settings (PF.Base/module/user/include/component/controller/login.class.php
 * and register.class.php both already check
 * Phpfox::getParam('user.redirect_after_login' / '...redirect_after_signup')
 * and use it verbatim if non-empty - this is a sanctioned, already-wired
 * extension point, not a core file edit) directly via the :setting table -
 * the same row a "Save" click on Admincp > Settings > User would write.
 *
 * Uses a full absolute URL rather than a bare path/module name: both
 * controllers special-case an absolute URL with url()->forward($sReturn),
 * bypassing phpFox's legacy named-route resolution
 * (trim/dot-replace + url()->send()) entirely - the safer choice here
 * since /find-your-industry is a modern Core\Route path, not a
 * legacy-registered URL name.
 *
 * :setting rows are cached under cache key 'setting'
 * (PF.Base/include/library/phpfox/setting/setting.class.php::set()) -
 * cleared here the same way :language_phrase's cache is cleared in
 * sync-phrases.php. As with every other cache clear this session, the
 * clear only affects the worker that runs THIS script; hit
 * /admincp/maintain/cache?all=1 several times over real HTTP afterward to
 * reach the other PHP-FPM workers before verifying.
 *
 * Usage: php set-login-redirect.php            (sets both)
 *        php set-login-redirect.php --clear    (clears both back to empty)
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

$bClear = $isCli && in_array('--clear', $argv, true);
$sTarget = $bClear ? '' : 'https://partnershipportal.hulahoot.com/find-your-industry';

$db = Phpfox::getLib('database');

foreach (['redirect_after_login', 'redirect_after_signup'] as $sVarName) {
    $db->update(':setting', ['value_actual' => $sTarget], ['module_id' => 'user', 'var_name' => $sVarName]);
}

$oCache = Phpfox::getLib('cache');
$oCache->remove($oCache->set('setting'));

$out(($bClear ? 'Cleared' : 'Set') . ' user.redirect_after_login / user.redirect_after_signup'
    . ($bClear ? '' : ' to: ' . $sTarget)
    . '. Local cache cleared - hit /admincp/maintain/cache?all=1 several times over real HTTP next to reach every PHP-FPM worker before verifying.');
