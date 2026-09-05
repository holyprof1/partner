<?php

namespace Apps\Hulahoot\Service;

/**
 * Class UploadStorage
 *
 * Defence-in-depth for this app's own upload directories: drops a
 * restrictive .htaccess into a storage folder so a user-supplied file can
 * never be served as an executable script, whatever it was named on the
 * way in.
 *
 * Why this is needed even though the stored filename is already safe:
 * Phpfox_File always rewrites an upload to "<md5-hash>.<validated-ext>",
 * so a .php can't be written through the normal path. This guards the
 * remaining cases - a future caller passing a looser extension list, a
 * mis-set handler, or a server-level mapping that treats a double
 * extension as executable. Cheap, and it fails closed.
 *
 * It matters more for video than for images: Phpfox_File verifies real
 * IMAGE content (Phpfox_Image::isImage()) but has no video equivalent, so
 * video relies more heavily on the extension allowlist. Both of this
 * app's upload folders are hardened anyway, since the cost is one file.
 *
 * Deliberately uses only core Apache 2.4 directives (mod_mime /
 * mod_authz_core). It specifically does NOT use "php_flag engine off":
 * this server runs PHP-FPM, where php_flag is not a recognised directive
 * and would make Apache return 500 for the whole directory.
 *
 * @package Apps\Hulahoot\Service
 */
class UploadStorage
{
    const MARKER = '# Managed by Apps\Hulahoot\Service\UploadStorage';

    /**
     * Ensures $sDir exists and carries the hardening .htaccess.
     *
     * Safe to call on every upload: it writes only when the file is
     * missing or was not written by this class, so an operator's own
     * customisations are never clobbered.
     *
     * @param string $sDir absolute directory path
     *
     * @return void
     */
    public static function harden($sDir)
    {
        if (!is_dir($sDir)) {
            @mkdir($sDir, 0755, true);
        }

        if (!is_dir($sDir) || !is_writable($sDir)) {
            // Nothing sensible to do - the upload itself will fail
            // and report properly. Never fatal here.
            return;
        }

        $sFile = rtrim($sDir, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';

        if (file_exists($sFile)) {
            $sCurrent = (string)@file_get_contents($sFile);

            // Already ours - nothing to do.
            if (strpos($sCurrent, self::MARKER) !== false) {
                return;
            }

            // Someone else's file - never clobber an operator's own rules.
            if (trim($sCurrent) !== '') {
                return;
            }
        }

        @file_put_contents($sFile, self::_contents());
    }

    /**
     * @return string
     */
    private static function _contents()
    {
        return self::MARKER . " - do not edit.\n"
            . "# User-uploaded media. Must never be executed or listed.\n"
            . "Options -Indexes -ExecCGI\n"
            . "\n"
            . "<FilesMatch \"\\.(?i:ph(?:p[0-9]?|tml|ar|ps)|cgi|pl|py|jsp|asp|aspx|sh|bash|exe|htaccess)$\">\n"
            . "    <IfModule mod_authz_core.c>\n"
            . "        Require all denied\n"
            . "    </IfModule>\n"
            . "    <IfModule !mod_authz_core.c>\n"
            . "        Order allow,deny\n"
            . "        Deny from all\n"
            . "    </IfModule>\n"
            . "</FilesMatch>\n";
    }
}
