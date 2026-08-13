<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * Read-only. Proof that SWESS decisions are actually enforced, not just
 * configured - see Service\Swess::canPostAs()'s own docblock.
 */
class SwessAuditController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template(), \Apps\Hulahoot\Service\AdmincpChrome::swessLinks());

        $service = new \Apps\Hulahoot\Service\Swess();

        // Pre-formatted here, not in the template - the date modifier
        // this classic Smarty-style template engine actually supports
        // isn't documented anywhere in this codebase, and every other
        // controller that formats a timestamp for display (e.g.
        // WalletController) already does it here rather than risk a
        // template-compile-time surprise over one modifier call.
        $aLog = $service->listAuditLog(200);
        foreach ($aLog as &$aRow) {
            $aRow['created_display'] = Phpfox::getLib('date')->convertTime((int)$aRow['created'], 'core.global_update_time');
        }
        unset($aRow);

        $this->template()->setTitle(_p('hulahoot_admin_swess_audit'))
            ->setBreadCrumb(_p('hulahoot_admin_swess_audit'))
            ->assign([
                'log' => $aLog,
            ]);
    }

    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_swess_audit_clean')) ? eval($sPlugin) : false);
    }
}
