<?php
/**
 * Sync phrase.json into the :language_phrase table for every active
 * language.
 *
 * phrase.json is never read live on a normal (non-"Techie") request -
 * Core\Phrase::get() (PF.Src/Core/Phrase.php) only falls back to
 * scanning phrase.json when Phpfox::isTechie() is true, and even then it
 * only auto-inserts a row for a var_name that doesn't exist in
 * :language_phrase yet - editing the TEXT of an already-imported phrase
 * never gets picked up that way at all, regardless of Techie mode.
 * :language_phrase is also served from a ~24h cache
 * ('language_phrase_all'), independent of the generic site cache clear
 * (Phpfox::getLib('cache')->remove()).
 *
 * Run this after every phrase.json change: it upserts every key for
 * every active language (phrase.json in this app only ever defines one
 * value per key - English - so every language gets that same text; no
 * per-language override exists here), then explicitly clears the phrase
 * cache.
 *
 * Usage: php sync-phrases.php
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

$aPhrases = json_decode(file_get_contents(__DIR__ . '/phrase.json'), true);

if (!is_array($aPhrases)) {
    $out('ERROR: phrase.json is missing or invalid.');
    exit(1);
}

$db = Phpfox::getLib('database');
$aLanguages = (array)$db->select('language_id')->from(':language')->execute('getSlaveRows');

$iInserted = 0;
$iUpdated = 0;

foreach ($aLanguages as $aLanguage) {
    $sLanguageId = $aLanguage['language_id'];

    foreach ($aPhrases as $sVarName => $sText) {
        $sClean = Phpfox_Parse_Input::instance()->clean($sText);

        $aExisting = $db->select('phrase_id, text')
            ->from(':language_phrase')
            ->where(['language_id' => $sLanguageId, 'var_name' => $sVarName])
            ->execute('getSlaveRow');

        if (!$aExisting) {
            $db->insert(':language_phrase', [
                'language_id' => $sLanguageId,
                'var_name' => $sVarName,
                'text' => $sClean,
                'text_default' => $sClean,
                'added' => time(),
            ]);
            $iInserted++;
        } elseif ($aExisting['text'] !== $sClean) {
            $db->update(':language_phrase', ['text' => $sClean], ['phrase_id' => (int)$aExisting['phrase_id']]);
            $iUpdated++;
        }
    }
}

// Matches Core\Phrase::init()'s own cache-id derivation exactly
// (PF.Src/Core/Phrase.php) - 'language_phrase_all' alone is not the real
// cache key, $oCache->set() namespaces it first.
$oCache = Phpfox::getLib('cache');
$oCache->remove($oCache->set('language_phrase_all'));

$out('Phrase sync complete: ' . count($aPhrases) . ' keys x ' . count($aLanguages) . ' language(s) - '
    . $iInserted . ' inserted, ' . $iUpdated . ' updated.');
