<?php

namespace Apps\Hulahoot\Service;

use Phpfox;
use Phpfox_File;

/**
 * Class VideoUpload
 *
 * Milestone 2's composer "Video" requirement (master plan item 2, listed
 * alongside "Photos" as its own field).
 *
 * Deliberately a structural sibling of Service\ImageUpload - same thin
 * wrapper around the same native Phpfox_File::load()/upload() pair, same
 * stored-path convention, same resolveUrl() shape. Phpfox_File is not
 * image-specific: it validates against a caller-supplied extension list
 * and only applies image processing (real-image verification, orientation
 * fix) when the extension is a known image type, so a video runs through
 * exactly the same hardened path every other upload in this app already
 * uses - unique md5-hashed filenames, native per-user disk-quota check,
 * move_uploaded_file(). No parallel upload architecture is introduced.
 *
 * NOT connected to Hulahoot/ShaunSocial publishing, and deliberately NOT
 * routed through the bundled core-videos FFmpeg/transcoding pipeline.
 * Master plan item 37 (Responsibility Split) assigns "Media ingestion
 * needed for Portal publishing" to the ShaunSocial side, not the Portal -
 * so Milestone 2's job is to store the file safely in Portal-owned storage
 * and associate it with the SWESS post, leaving ingestion/transcoding to
 * the later publishing milestone.
 *
 * Stores under PHPFOX_DIR_FILE.'video/hulahoot/' - a sibling of
 * ImageUpload's 'pic/hulahoot/', keeping SWESS video isolated from both
 * the app's own images and the native video module's storage.
 *
 * @package Apps\Hulahoot\Service
 */
class VideoUpload
{
    const UPLOAD_DIR = PHPFOX_DIR_FILE . 'video' . PHPFOX_DS . 'hulahoot' . PHPFOX_DS;

    /**
     * Fallback when hulahoot.swess_max_video_mb is unset/invalid - same
     * "sane constant behind an admin setting" shape CreditLedger uses for
     * its own DEFAULT_CREDITS_PER_POST.
     */
    const DEFAULT_MAX_MB = 50;

    /**
     * Extensions accepted for a SWESS video.
     *
     * Every one of these is (a) present in Phpfox_File's own MIME map, so
     * the platform already knows the type, and (b) playable natively in a
     * browser <video> element. That second condition matters because
     * Milestone 2 has no transcoding step by design (see class docblock) -
     * whatever is stored is what the composer and post-detail screens play
     * back directly.
     *
     * Deliberately EXCLUDES avi/flv/wmv/mkv/mpg: they appear in
     * Phpfox_File's MIME map but are not reliably playable in a browser
     * without transcoding, and transcoding is ShaunSocial-side work in a
     * later milestone.
     *
     * @return string[]
     */
    public static function getAllowedExtensions()
    {
        return ['mp4', 'm4v', 'webm', 'ogv', 'mov'];
    }

    /**
     * The effective per-file ceiling in bytes.
     *
     * Clamped to PHP's own upload_max_filesize: PHP rejects an oversized
     * upload before any application code runs, so advertising a larger
     * limit than the server will physically accept would only produce a
     * confusing "no file was submitted" instead of an honest size error.
     *
     * @return int
     */
    public static function getMaxBytes()
    {
        $iConfiguredMb = (int)Phpfox::getParam('hulahoot.swess_max_video_mb');

        if ($iConfiguredMb <= 0) {
            $iConfiguredMb = self::DEFAULT_MAX_MB;
        }

        $iAppLimit = $iConfiguredMb * 1024 * 1024;
        $iPhpLimit = self::_iniBytes(ini_get('upload_max_filesize'));

        return ($iPhpLimit > 0 && $iPhpLimit < $iAppLimit) ? $iPhpLimit : $iAppLimit;
    }

    /**
     * Validates and moves an uploaded video for $sFormField, if one was
     * actually submitted. Mirrors Service\ImageUpload::upload()'s contract
     * exactly so callers can treat the two identically.
     *
     * @param string $sFormField
     *
     * @return string|null The stored path (with the literal %s size-suffix
     *         placeholder native uploads use - resolve via resolveUrl()),
     *         or null if no file was submitted for this field at all.
     *
     * @throws \InvalidArgumentException if a file was submitted but is
     *         invalid (wrong extension, oversized, or its real content
     *         isn't actually a video)
     */
    public function upload($sFormField)
    {
        if (empty($_FILES[$sFormField]['name'])) {
            return null;
        }

        $aAllowed = self::getAllowedExtensions();
        $iMaxBytes = self::getMaxBytes();

        // Checked before Phpfox_File so an oversized file produces a clear
        // size error naming the actual limit, rather than the generic
        // extension message load() falls back to.
        if (!empty($_FILES[$sFormField]['size']) && (int)$_FILES[$sFormField]['size'] > $iMaxBytes) {
            throw new \InvalidArgumentException(_p('hulahoot_swess_video_too_large', [
                'limit' => (int)floor($iMaxBytes / 1024 / 1024),
            ]));
        }

        // Content-based check on top of Phpfox_File's extension check.
        // Phpfox_File verifies real content only for IMAGE extensions
        // (Phpfox_Image::isImage()); there is no video equivalent, so a
        // renamed non-video would otherwise pass on extension alone. The
        // stored filename is always md5-hashed with the validated
        // extension forced, so this can't become an executable file - but
        // rejecting non-video content outright keeps junk out of storage
        // and is the honest place to fail.
        $this->_assertRealVideo($_FILES[$sFormField]['tmp_name'] ?? null);

        $mLoaded = Phpfox_File::instance()->load($sFormField, $aAllowed, $iMaxBytes);

        if ($mLoaded === false) {
            throw new \InvalidArgumentException(_p('hulahoot_swess_video_upload_invalid', [
                'support' => implode(', ', $aAllowed),
            ]));
        }

        $sStoredPath = Phpfox_File::instance()->upload($sFormField, self::UPLOAD_DIR, uniqid());

        if ($sStoredPath === false) {
            throw new \InvalidArgumentException(_p('hulahoot_swess_video_upload_failed'));
        }

        return $sStoredPath;
    }

    /**
     * Resolves a stored path to a root-relative public URL - same
     * reasoning and same shape as Service\ImageUpload::resolveUrl(),
     * pointed at this class's own storage directory.
     *
     * @param string|null $sStoredPath
     *
     * @return string|null
     */
    public function resolveUrl($sStoredPath)
    {
        if (empty($sStoredPath)) {
            return null;
        }

        $sFileName = strpos($sStoredPath, '%s') !== false ? sprintf($sStoredPath, '') : $sStoredPath;

        return '/PF.Base/file/video/hulahoot/' . ltrim($sFileName, '/\\');
    }

    /**
     * @param string|null $sTmpPath
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the file's real MIME type isn't a video
     */
    private function _assertRealVideo($sTmpPath)
    {
        if (empty($sTmpPath) || !is_readable($sTmpPath) || !function_exists('finfo_open')) {
            // No fileinfo extension available - fall through to
            // Phpfox_File's extension check rather than blocking a
            // legitimate upload on a missing optional PHP extension.
            return;
        }

        $oFinfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($oFinfo === false) {
            return;
        }

        $sMime = finfo_file($oFinfo, $sTmpPath);
        finfo_close($oFinfo);

        // Some containers (notably .mov and some .mp4) are reported as
        // application/octet-stream by older libmagic builds; those are
        // still gated by the extension allowlist above, so only an
        // affirmatively NON-video, NON-generic type is rejected here.
        if ($sMime !== false && strpos($sMime, 'video/') !== 0 && $sMime !== 'application/octet-stream') {
            throw new \InvalidArgumentException(_p('hulahoot_swess_video_upload_invalid', [
                'support' => implode(', ', self::getAllowedExtensions()),
            ]));
        }
    }

    /**
     * Parses a PHP ini shorthand size ("2M", "512K", "1G") to bytes.
     *
     * @param string|false $sValue
     *
     * @return int 0 when unparseable/unlimited
     */
    private static function _iniBytes($sValue)
    {
        if (empty($sValue)) {
            return 0;
        }

        $sValue = trim((string)$sValue);
        $iNumber = (int)$sValue;

        switch (strtolower(substr($sValue, -1))) {
            case 'g':
                return $iNumber * 1024 * 1024 * 1024;
            case 'm':
                return $iNumber * 1024 * 1024;
            case 'k':
                return $iNumber * 1024;
            default:
                return $iNumber;
        }
    }
}
