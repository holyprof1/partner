<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * /admincp/hulahoot/swess - index redirect, matching the same
 * "bare group index sends you to the first real page" pattern the
 * admincp/hulahoot group route itself already uses in start.php.
 */
class SwessController extends Phpfox_Component
{
    public function process()
    {
        $this->url()->send('/admincp/hulahoot/swess/whitelist');
    }

    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_swess_clean')) ? eval($sPlugin) : false);
    }
}
