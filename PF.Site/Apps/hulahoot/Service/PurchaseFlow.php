<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class PurchaseFlow
 *
 * Initiates a subscription purchase from the Industry package cards,
 * calling the native Core Subscriptions purchase services directly
 * (Service\Purchase\Process::add()/update() - the exact same calls
 * Apps\Core_Subscriptions\Block\UpgradeBlock makes) rather than
 * reimplementing any billing logic. Exists only to avoid one thing
 * UpgradeBlock does that's wrong for Hulahoot's packages specifically:
 * it hard-blocks with "attempting_to_upgrade_to_the_same_user_group_you_are_already_in"
 * whenever the buyer's current group already equals the package's
 * configured user_group_id - and since every Hulahoot package is
 * configured to grant group 2 (Registered Member, chosen specifically as
 * a no-op for an ordinary customer - see seed-demo-data.php), that guard
 * fires for essentially every real customer on every package. Native
 * Core Subscriptions has no concept of "grant no group at all", so this
 * class always passes the buyer's own current group back in as the
 * target - a genuine no-op regardless of what group they started in,
 * matching the explicit decision that purchasing a business package must
 * never change the buyer's phpFox account group.
 *
 * Paid packages are hard-handed to the native gateway-selection page
 * (subscribe.register, Apps\Core_Subscriptions\Controller\RegisterController)
 * once the purchase row exists - confirmed by reading that controller
 * directly that it has no equivalent group-collision guard - so gateway
 * selection, payment processing, and completion all stay entirely
 * native from that point on.
 *
 * Before doing that hand-off, a paid purchase also checks that at least
 * one payment gateway (api_gateway.is_active = 1 - the same row set
 * Apps\Core\Block\Gateway\Form reads via the api.gateway service) is
 * actually active. With zero active gateways the native gateway-
 * selection page has nothing to render - just an empty page - and a
 * customer landing there has no way forward except wandering into
 * native Core Subscriptions pages Hulahoot never wants them to see
 * (confirmed live: no gateway was active, and a customer ended up on
 * the raw native "Membership Packages" browse page). Failing here
 * instead sends them back to the Industry page with a clear message,
 * before a purchase row (or worse, a stray pending one) is even
 * created.
 *
 * @package Apps\Hulahoot\Service
 */
class PurchaseFlow
{
    /**
     * @param int $iUserId
     * @param int $iPackageId
     *
     * @return array{free: bool, purchase_id: int}
     *
     * @throws \InvalidArgumentException if the package doesn't exist,
     *         isn't currently purchasable, or (for a paid package) no
     *         payment gateway is active yet
     */
    public function initiate($iUserId, $iPackageId)
    {
        if (!Phpfox::isAppActive('Core_Subscriptions')) {
            throw new \InvalidArgumentException(_p('hulahoot_subscriptions_app_inactive'));
        }

        $aPackage = Phpfox::getService('subscribe')->getPackage((int)$iPackageId, true);

        if (!$aPackage || !$aPackage['is_active'] || !empty($aPackage['is_removed'])) {
            throw new \InvalidArgumentException(_p('hulahoot_subscription_package_not_found'));
        }

        $bFree = ((float)$aPackage['default_cost'] === 0.0);

        if (!$bFree && !$this->hasActiveGateway()) {
            throw new \InvalidArgumentException(_p('hulahoot_no_payment_gateway_active'));
        }

        $aUser = db()->select('user_group_id')
            ->from(':user')
            ->where(['user_id' => (int)$iUserId])
            ->execute('getSlaveRow');
        $iCurrentGroupId = (int)($aUser['user_group_id'] ?? 0);

        $iPurchaseId = Phpfox::getService('subscribe.purchase.process')->add([
            'package_id' => (int)$iPackageId,
            'currency_id' => $aPackage['default_currency_id'],
            'price' => $aPackage['default_cost'],
            'renew_type' => 0,
        ], (int)$iUserId);

        if ($bFree) {
            // Same call UpgradeBlock makes for a free package, with one
            // difference: the current group, not the package's
            // configured one, so this can never change it.
            Phpfox::getService('subscribe.purchase.process')->update(
                $iPurchaseId,
                (int)$iPackageId,
                'completed',
                (int)$iUserId,
                $iCurrentGroupId
            );

            return ['free' => true, 'purchase_id' => $iPurchaseId];
        }

        // Paid: leave status as-is (pending) and hand off to the native
        // gateway-selection page - same bookkeeping call UpgradeBlock
        // makes before doing that handoff itself.
        Phpfox::getService('subscribe.purchase.process')->changePurchaseForSigningUp($iPurchaseId, (int)$iUserId);

        return ['free' => false, 'purchase_id' => $iPurchaseId];
    }

    /**
     * @return bool
     */
    private function hasActiveGateway()
    {
        return (bool)db()->select('COUNT(*)')
            ->from(':api_gateway')
            ->where(['is_active' => 1])
            ->execute('getSlaveField');
    }
}
