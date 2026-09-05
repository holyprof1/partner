<?php
/**
 * Fires inside Apps\Core_Subscriptions\Controller\RegisterController::
 * process() (PF.Site/Apps/core-subscriptions/Controller/RegisterController.php),
 * an official phpFox extension point - Phpfox_Plugin::get('subscribe.
 * component_controller_register__1') + eval(), same filesystem-scanned
 * hooks/ mechanism as every other hook in this app. No core/native file
 * touched to add this.
 *
 * The problem: native RegisterController builds the checkout form's
 * 'gateway_data' (amount, currency, item name, etc. - everything the
 * gateway driver actually charges) from $aPurchase['default_cost'],
 * which is the PACKAGE's own fixed native price (confirmed by reading
 * Service\Purchase\Purchase::getInvoice()/_build() directly - it's
 * derived from the package's serialized cost, with no way to override it
 * per-purchase). That's correct for every ordinary purchase, but wrong
 * for a Hulahoot "Buy Out Remaining Slots" aggregated purchase
 * (Service\PurchaseFlow::buyOutRemainingSlots()), whose whole point is
 * charging N x the unit price in one checkout - the purchase row itself
 * already has that total stored in its own 'price' column (set at
 * creation time), completely ignored by native checkout-amount building.
 *
 * The fix: this hook fires immediately after native code calls
 * $this->setParam('gateway_data', [...]) with the native per-unit
 * amount, but still inside the SAME component method, with $this still
 * bound to the RegisterController instance - so calling
 * $this->setParam('gateway_data', [...]) again here (setParam() is a
 * plain array-key overwrite, confirmed by reading component.class.php
 * directly - no merge, no side effect from calling it twice) cleanly
 * replaces what the buyer is actually about to be charged, before the
 * checkout page ever renders. Every field below mirrors exactly what
 * native code just set, with only 'amount', 'item_name', and
 * 'alternative_cost' scaled - so this only ever changes WHAT is charged
 * for a Hulahoot Buy Out purchase specifically, never HOW checkout works
 * for anything else. A purchase that isn't a recorded Buy Out anchor
 * (hulahoot_purchase_buyout) is left completely untouched - $aPurchase
 * here covers every purchase that reaches this native controller, paid
 * or free, Hulahoot or not.
 *
 * On the completion side, Callback.php verifies total_paid against this
 * purchase row's own stored 'price' (getPurchase()'s sp.*, confirmed no
 * package-join collision on that column) - which PurchaseFlow already
 * set to this same N x unit total at creation time. Charging this amount
 * and verifying against that price are therefore already in agreement;
 * this hook doesn't need to (and doesn't) touch anything on the
 * completion/webhook side.
 */
if (!defined('PHPFOX_INSTALLER') && isset($aPurchase['purchase_id'])) {
    try {
        $aBuyout = db()->select('slot_count')
            ->from(':hulahoot_purchase_buyout')
            ->where(['purchase_id' => (int)$aPurchase['purchase_id']])
            ->execute('getSlaveRow');

        if ($aBuyout && (int)$aBuyout['slot_count'] > 1 && isset($aPurchase['default_cost'])) {
            $iSlotCount = (int)$aBuyout['slot_count'];
            $fAmount = round((float)$aPurchase['default_cost'] * $iSlotCount, 2);

            $sAlternativeCost = isset($aPurchase['cost']) ? $aPurchase['cost'] : null;
            if (!empty($aPurchase['cost']) && Phpfox::getLib('parse.format')->isSerialized($aPurchase['cost'])) {
                $aOriginalCosts = unserialize($aPurchase['cost']);
                if (is_array($aOriginalCosts)) {
                    $aScaledCosts = [];
                    foreach ($aOriginalCosts as $sCurrencyCode => $fUnitCost) {
                        $aScaledCosts[$sCurrencyCode] = (string)round((float)$fUnitCost * $iSlotCount, 2);
                    }
                    $sAlternativeCost = serialize($aScaledCosts);
                }
            }

            $sAlternativeRecurringCost = isset($aPurchase['recurring_cost']) ? $aPurchase['recurring_cost'] : '';
            $iRecurring = !empty($aPurchase['recurring_period'])
                ? ((isset($iRenewMethod) && (int)$iRenewMethod == 2) ? 0 : $aPurchase['recurring_period'])
                : 0;

            $this->setParam('gateway_data', [
                'item_number' => 'subscribe|' . $aPurchase['purchase_id'],
                'currency_code' => $aPurchase['default_currency_id'],
                'amount' => $fAmount,
                'item_name' => _p($aPurchase['title']) . ' x' . $iSlotCount,
                'return' => $this->url()->makeUrl('subscribe.complete'),
                'recurring' => $iRecurring,
                'recurring_cost' => (isset($aPurchase['default_recurring_cost']) ? $aPurchase['default_recurring_cost'] : ''),
                'alternative_cost' => $sAlternativeCost,
                'alternative_recurring_cost' => $sAlternativeRecurringCost,
            ]);

            Phpfox::getLog('hulahoot.log')->info(
                'Hulahoot buy-out checkout: overrode gateway charge amount for purchase '
                . $aPurchase['purchase_id'] . ' to ' . $fAmount . ' ' . $aPurchase['default_currency_id']
                . ' (' . $iSlotCount . ' slots x unit price ' . $aPurchase['default_cost'] . ').'
            );
        }
    } catch (\Throwable $e) {
        Phpfox::getLog('hulahoot.log')->error(
            'Hulahoot buy-out checkout: failed to override gateway amount for purchase '
            . (isset($aPurchase['purchase_id']) ? $aPurchase['purchase_id'] : '?') . ': ' . $e->getMessage()
        );
    }
}
