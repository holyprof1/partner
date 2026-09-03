<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * Milestone 2: the AdminCP surface for Service\CreditLedger's "Correct
 * credits" requirement - search for a user, see their real balance
 * (package allocation, bonus, reserved, used, available) and full credit
 * history, and grant/revoke a bonus adjustment with a required note.
 *
 * Same "one focused sub-page, several small POST actions discriminated by
 * a 'do' field" shape as SwessWhitelistAddController - reuses that
 * screen's own type-ahead user-search widget verbatim (same endpoint,
 * same inline script) rather than building a second one.
 */
class SwessCreditController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template(), \Apps\Hulahoot\Service\AdmincpChrome::swessLinks());

        $service = new \Apps\Hulahoot\Service\CreditLedger();
        $req = $this->request();
        $error = null;
        $iActorUserId = Phpfox::getUserId();

        $iUserId = (int)$req->get('user_id');
        $aOwner = $iUserId
            ? db()->select('user_id, user_name, full_name')->from(':user')->where(['user_id' => $iUserId])->execute('getSlaveRow')
            : null;

        if ($iUserId && !$aOwner) {
            $this->url()->send('/admincp/hulahoot/swess/credits', [], _p('hulahoot_swess_user_not_found'));
        }

        if ($aOwner && $req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                try {
                    $sDo = (string)$req->get('do');

                    if ($sDo === 'adjust_bonus') {
                        $sDirection = (string)$req->get('direction') === 'revoke' ? -1 : 1;
                        $iAmount = abs((int)$req->get('amount')) * $sDirection;

                        $service->adjustBonus($iUserId, $iAmount, $iActorUserId, (string)$req->get('note'));

                        $this->url()->send('/admincp/hulahoot/swess/credits', ['user_id' => $iUserId], _p('hulahoot_swess_credit_adjusted'));
                    }
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        // Pre-formatted here, not via a Smarty date modifier in the
        // template - matches the established convention this app already
        // uses for the SWESS post-detail timeline (see start.php's
        // /hulahoot/swess/posts/view route), rather than assuming a
        // Smarty date modifier is configured/available.
        $aLedger = $aOwner ? $service->getLedgerForUser($iUserId, 50) : [];
        foreach ($aLedger as &$aTx) {
            $aTx['created_display'] = Phpfox::getLib('date')->convertTime((int)$aTx['created'], 'core.global_update_time');
        }
        unset($aTx);

        $this->template()->setTitle(_p('hulahoot_admin_swess_credits'))
            ->setBreadCrumb(_p('hulahoot_admin_swess_credits'))
            ->assign([
                'owner' => $aOwner,
                'balance' => $aOwner ? $service->getBalance($iUserId) : null,
                'ledger' => $aLedger,
                'error' => $error,
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_swess_credit_clean')) ? eval($sPlugin) : false);
    }
}
