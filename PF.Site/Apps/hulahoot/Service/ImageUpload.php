<?php

namespace Apps\Hulahoot\Service;

use Phpfox;
use Phpfox_File;

/**
 * Class ImageUpload
 *
 * Thin, shared wrapper around the native Phpfox_File upload pipeline
 * (the same Phpfox_File::instance()->load()/upload() pair
 * Apps\Core_Subscriptions\Service\Process::add() uses for its own package
 * image) - reused here rather than reimplemented so Hulahoot picks up the
 * same validation, unique-hashed filenames, and image-orientation
 * handling every other upload in this codebase gets for free.
 *
 * Stores everything under PHPFOX_DIR_FILE.'pic/hulahoot/', matching the
 * established per-app 'pic/{app}/' convention (see subscribe.dir_image).
 * No thumbnail variant is auto-generated - callers that need one (e.g. a
 * banner vs. a thumbnail) upload each as its own field, since Phase 2's
 * Industry/Package forms already collect them separately.
 *
 * @package Apps\Hulahoot\Service
 */
class ImageUpload
{
    const UPLOAD_DIR = PHPFOX_DIR_FILE . 'pic' . PHPFOX_DS . 'hulahoot' . PHPFOX_DS;

    /**
     * Validates and moves an uploaded file for $sFormField (an <input
     * type="file" name="$sFormField">), if one was actually submitted.
     *
     * @param string $sFormField
     * @param string[] $aAllowedExtensions
     *
     * @return string|null The stored path (with a literal %s placeholder
     *         for the size-suffix slot native uploads use - resolve it
     *         with sprintf($path, '') to get the real file), or null if
     *         no file was submitted for this field at all.
     *
     * @throws \InvalidArgumentException if a file was submitted but is
     *         invalid (wrong extension, not a real image, etc.)
     */
    public function upload($sFormField, array $aAllowedExtensions = ['jpg', 'jpeg', 'png', 'gif'])
    {
        if (empty($_FILES[$sFormField]['name'])) {
            return null;
        }

        // Same defence-in-depth applied to the video folder - see
        // Service\UploadStorage. No behaviour change to the upload itself.
        UploadStorage::harden(self::UPLOAD_DIR);

        $mLoaded = Phpfox_File::instance()->load($sFormField, $aAllowedExtensions);

        if ($mLoaded === false) {
            throw new \InvalidArgumentException(_p('hulahoot_image_upload_invalid', ['support' => implode(', ', $aAllowedExtensions)]));
        }

        $sStoredPath = Phpfox_File::instance()->upload($sFormField, self::UPLOAD_DIR, uniqid());

        if ($sStoredPath === false) {
            throw new \InvalidArgumentException(_p('hulahoot_image_upload_failed'));
        }

        return $sStoredPath;
    }

    /**
     * Resolves a stored path (as returned by upload(), or read back from
     * the database) to a root-relative public URL - deliberately not an
     * absolute URL: PHPFOX_DIR_FILE always maps to '/PF.Base/file/'
     * relative to the site root (verified directly against
     * subscribe.dir_image/url_image's own filesystem-path/URL pair), and
     * a root-relative path resolves correctly regardless of domain or
     * protocol without needing any site-base-URL param at all.
     *
     * Safe to call on an empty/null path - returns null so templates can
     * just do {if $url = ...->resolveUrl($industry.banner)}.
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

        return '/PF.Base/file/pic/hulahoot/' . ltrim($sFileName, '/\\');
    }
}
