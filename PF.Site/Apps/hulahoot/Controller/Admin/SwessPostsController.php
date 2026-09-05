<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * The SWESS Posts inspection screen - every post across every publisher,
 * filterable by status.
 *
 * Master plan item 32 requires administrators to be able to "Inspect
 * scheduled posts", and item 33 ("Validate All Pre-Launch Scheduled
 * Posts") depends on it. The Approval Queue
 * (Controller/Admin/SwessApprovalController) only ever shows 'pending',
 * because it is a review/action surface; this is the complementary
 * read-only view over everything else.
 *
 * READ-ONLY BY DESIGN. This controller has no POST branch and calls no
 * mutating service method: it must not be able to disturb the verified
 * Milestone 2 lifecycle or credit ledger. Administrator actions on
 * another publisher's post (suspend/cancel) are deliberately NOT here -
 * that requirement carries a genuine Portal-vs-Hulahoot scope ambiguity
 * and is awaiting a decision rather than being assumed.
 *
 * @package Apps\Hulahoot\Controller\Admin
 */
class SwessPostsController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template(), \Apps\Hulahoot\Service\AdmincpChrome::swessLinks());

        $service = new \Apps\Hulahoot\Service\Swess();

        // Anything not in POST_STATUSES is treated as "all" by
        // getPostsForAdmin(); normalised here too so the template can
        // highlight the active filter honestly.
        $sStatus = (string)$this->request()->get('status');
        if (!in_array($sStatus, \Apps\Hulahoot\Service\Swess::POST_STATUSES, true)) {
            $sStatus = '';
        }

        $aPosts = $service->getPostsForAdmin($sStatus !== '' ? $sStatus : null);

        $oDate = Phpfox::getLib('date');
        foreach ($aPosts as &$aRow) {
            // Pre-formatted server-side rather than via a Smarty date
            // modifier - the same convention the Approval Queue and the
            // SWESS Credits screen already use in this AdminCP.
            $aRow['created_display'] = $oDate->convertTime((int)$aRow['created'], 'core.global_update_time');
            $aRow['updated_display'] = $oDate->convertTime((int)$aRow['updated'], 'core.global_update_time');
            $aRow['scheduled_display'] = !empty($aRow['scheduled_at'])
                ? $oDate->convertTime((int)$aRow['scheduled_at'], 'core.global_update_time')
                : null;
            $aRow['content_preview'] = mb_strimwidth((string)$aRow['content'], 0, 140, '...');
        }
        unset($aRow);

        $this->template()->setTitle(_p('hulahoot_admin_swess_posts'))
            ->setBreadCrumb(_p('hulahoot_admin_swess_posts'))
            ->assign([
                'posts' => $aPosts,
                'counts' => $service->getPostCountsByStatus(),
                'statuses' => \Apps\Hulahoot\Service\Swess::POST_STATUSES,
                'active_status' => $sStatus,
                'total_shown' => count($aPosts),
            ]);
    }

    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_swess_posts_clean')) ? eval($sPlugin) : false);
    }
}
