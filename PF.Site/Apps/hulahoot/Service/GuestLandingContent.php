<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class GuestLandingContent
 *
 * The guest homepage's actual markup (hero, videos, CTA - everything
 * inside <main class="hh-page">) is admin-editable HTML, not a hardcoded
 * template - requested directly: "let it be there like before so the
 * admin can edit... it can be just html like before". "Before" turned
 * out to be a genuine, already-installed native mechanism: a Custom HTML
 * block (block_id GUEST_LANDING_BLOCK_ID, native table :block, type_id=2)
 * that ships with the site's own "Guest Landing" app
 * (PF.Site/Apps/guestlanding, a FoxExpert app, not part of Hulahoot's own
 * code) - its content lives in the native :block_source table, edited
 * through the real, already-existing AdminCP screen at
 * /admincp/block/add/?id=GUEST_LANDING_BLOCK_ID ("Block Manager -> Add
 * New Block", editing block_id 232 specifically).
 *
 * That native app's own guestlanding.index controller was never actually
 * reachable here - Hulahoot's own '/' Core\Route registration always
 * wins over the classic dispatch resolution that would otherwise hand
 * guests to it (see start.php's own '/' route comment) - so the block
 * existed with real content the whole time, just orphaned. This class
 * is the bridge: the '/' route below renders this same block's stored
 * HTML inside Hulahoot's own themed wrapper (real header/nav/logo from
 * whichever flavor is active), instead of a copy hardcoded into this
 * app's git repo. Editing the block through AdminCP now takes effect on
 * the very next page load, no deploy required.
 *
 * @package Apps\Hulahoot\Service
 */
class GuestLandingContent
{
    const GUEST_LANDING_BLOCK_ID = 232;

    /**
     * @return string|null the admin-edited HTML, or null if the block
     *         has no content yet (caller should fall back to something
     *         reasonable rather than render an empty homepage)
     */
    public function getHtml()
    {
        $sHtml = db()->select('source_code')
            ->from(':block_source')
            ->where(['block_id' => self::GUEST_LANDING_BLOCK_ID])
            ->execute('getSlaveField');

        return $sHtml !== false && trim((string)$sHtml) !== '' ? $sHtml : null;
    }
}
