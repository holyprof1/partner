<?php

namespace Apps\Hulahoot\Controller;

use Phpfox;
use Phpfox_Component;

defined('PHPFOX') or exit('NO DICE!');

/**
 * Backs the "SWESS Wallet" profile tab (/username/hulahoot), reached via
 * the standard {module}.profile sub-section dispatch in
 * Profile_Component_Controller_Index::process() (same mechanism since
 * Phase 0 - see PHASE_1_SUBSCRIPTION.md - only the class name changed,
 * from ProfileRedirectController, since it now renders instead of
 * redirecting).
 *
 * Phase 1 scope only: reads and displays the user's current subscription
 * status via Service\Subscription (a thin wrapper over Core
 * Subscriptions' own service - no purchase/renewal/entitlement logic
 * lives here). Credits, SWESS entitlement, payments, and gateway logic
 * are explicitly out of scope - Credits renders a static placeholder
 * string only.
 */
class WalletController extends Phpfox_Component
{
    public function process()
    {
        $iUserId = Phpfox::getUserId();

        $aSubscription = (new \Apps\Hulahoot\Service\Subscription())->getStatusForUser($iUserId);

        // Pre-formatted here (not in the template) so the template doesn't
        // need to know phpFox's date-formatting convention - matches how
        // Core Subscriptions itself formats timestamps for display, e.g.
        // Service\Purchase\Purchase - see PHASE_1_SUBSCRIPTION.md.
        $aSubscription['expiry_date_display'] = $aSubscription['expiry_date']
            ? Phpfox::getLib('date')->convertTime($aSubscription['expiry_date'], 'core.global_update_time')
            : null;

        $this->template()
            ->setTitle(_p('hulahoot_swess_wallet'))
            ->setBreadCrumb(_p('hulahoot_swess_wallet'))
            ->assign([
                'subscription' => $aSubscription,
            ]);
    }
}
