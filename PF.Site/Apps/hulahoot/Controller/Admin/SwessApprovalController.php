<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * The Approval Queue - every SWESS post currently 'pending' review,
 * across every publisher (Service\Swess::getPendingPosts()), oldest
 * first. One screen, two actions: Approve or Reject (with a required
 * reason) - see Service\Swess::approvePost()/rejectPost() for what
 * each one actually does to the post's status.
 */
class SwessApprovalController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template(), \Apps\Hulahoot\Service\AdmincpChrome::swessLinks());

        $service = new \Apps\Hulahoot\Service\Swess();
        $req = $this->request();
        $error = null;
        $iActorUserId = Phpfox::getUserId();

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $iPostId = (int)$req->get('swess_post_id');
                $sDo = (string)$req->get('do');

                try {
                    if ($sDo === 'approve') {
                        $service->approvePost($iPostId, $iActorUserId);
                        $this->url()->send('/admincp/hulahoot/swess/approval', [], _p('hulahoot_swess_post_approved'));
                    } elseif ($sDo === 'reject') {
                        $sReason = trim((string)$req->get('rejection_reason'));
                        if ($sReason === '') {
                            throw new \InvalidArgumentException(_p('hulahoot_swess_rejection_reason_required'));
                        }
                        $service->rejectPost($iPostId, $sReason, $iActorUserId);
                        $this->url()->send('/admincp/hulahoot/swess/approval', [], _p('hulahoot_swess_post_rejected'));
                    }
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $aPending = $service->getPendingPosts();
        foreach ($aPending as &$aRow) {
            $aRow['created_display'] = Phpfox::getLib('date')->convertTime((int)$aRow['created'], 'core.global_update_time');
            $aRow['scheduled_display'] = $aRow['scheduled_at']
                ? Phpfox::getLib('date')->convertTime((int)$aRow['scheduled_at'], 'core.global_update_time')
                : null;
        }
        unset($aRow);

        $this->template()->setTitle(_p('hulahoot_admin_swess_approval'))
            ->setBreadCrumb(_p('hulahoot_admin_swess_approval'))
            ->assign([
                'pending' => $aPending,
                'error' => $error,
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_swess_approval_clean')) ? eval($sPlugin) : false);
    }
}
