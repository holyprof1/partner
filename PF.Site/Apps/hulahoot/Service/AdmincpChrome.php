<?php

namespace Apps\Hulahoot\Service;

/**
 * Class AdmincpChrome
 *
 * Every native AdminCP page reaches Apps\Core\Admincp\Controller\IndexController
 * (Admincp_Component_Controller_Index::process() in the classic module),
 * which unconditionally enqueues menu.css/menu.js/admin.js/drag.js/
 * jquery.mosaicflow.min.js before dispatching to the actual page.
 * Hulahoot's AdminCP routes call Phpfox::getLib('module')->dispatch()
 * directly on their own component (see start.php's admincp/hulahoot
 * group) rather than going through that Index controller, so those
 * assets were silently never enqueued - the page's own markup still
 * rendered, but the left-hand nav menu, which depends on menu.css/
 * menu.js to lay out and expand correctly, rendered as a collapsed,
 * broken sliver instead of the real sidebar. Confirmed live 2026-08-07
 * by diffing the asset list between a native AdminCP page and a
 * Hulahoot one - only these five files were missing.
 *
 * Call apply() from every Hulahoot AdminCP controller's process(), the
 * same five files Admincp_Component_Controller_Index::process() enqueues,
 * so every Hulahoot AdminCP page gets identical chrome to every native one.
 */
class AdmincpChrome
{
    /**
     * @param \Core\View|mixed $template Whatever $this->template() returns
     *        in a classic Phpfox_Component controller.
     */
    public static function apply($template)
    {
        $template->setHeader([
            'menu.css' => 'style_css',
            'menu.js' => 'style_script',
            'admin.js' => 'static_script',
            'drag.js' => 'static_script',
            'jquery/plugin/jquery.mosaicflow.min.js' => 'static_script',
        ]);
    }
}
