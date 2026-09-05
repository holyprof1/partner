<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

defined('PHPFOX') or exit('NO DICE!');

/**
 * Read-only status wrapper around Core Subscriptions - Phase 1 scope only
 * (see PHASE_1_SUBSCRIPTION.md). Does not implement SWESS entitlement,
 * credits, payments, or gateway logic; does not write anything to the
 * subscribe_* tables itself. Every purchase/renewal/expiration/user-group
 * write already lives in Apps\Core_Subscriptions\Service\Purchase\Process
 * - this class only reads what that app has already decided.
 */
class Subscription
{
    /**
     * The user's current subscription, if any, in a small normalized
     * shape the SWESS Wallet template can render without knowing
     * anything about Core Subscriptions' own table/column layout.
     *
     * Reuses Apps\Core_Subscriptions\Service\Purchase\Purchase::
     * getMySubscriptions() - the same service the native "My
     * Subscriptions" page itself calls - rather than querying
     * subscribe_purchase/subscribe_package directly, so Hulahoot never
     * has its own copy of that status logic to keep in sync. Filtered to
     * the single most recent status=completed row, matching how
     * Purchase\Process::update() already guarantees at most one
     * completed subscription per user (it auto-cancels the previous one
     * when a new purchase completes).
     *
     * @param int $iUserId
     *
     * @return array{
     *     has_plan: bool,
     *     package_id: int|null,
     *     title: string|null,
     *     status: string|null,
     *     expiry_date: int|null,
     *     period_label: string|null
     * }
     */
    public function getStatusForUser($iUserId)
    {
        $aEmpty = [
            'has_plan' => false,
            'package_id' => null,
            'title' => null,
            'status' => null,
            'expiry_date' => null,
            'period_label' => null,
        ];

        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            return $aEmpty;
        }

        $aRows = Phpfox::getService('subscribe.purchase')->getMySubscriptions(
            (int)$iUserId,
            ['status' => 'completed'],
            1,
            1
        );

        if (empty($aRows[0]['purchase_id'])) {
            return $aEmpty;
        }

        $aRow = $aRows[0];

        return [
            'has_plan' => true,
            'package_id' => (int)$aRow['package_id'],
            'title' => $aRow['title_parse'],
            'status' => $aRow['status'],
            'expiry_date' => (int)$aRow['expiry_date'] > 0 ? (int)$aRow['expiry_date'] : null,
            'period_label' => $aRow['type'],
        ];
    }
}
