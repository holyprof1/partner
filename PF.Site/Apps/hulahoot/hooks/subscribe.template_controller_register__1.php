<?php
/**
 * Fires inside PF.Site/Apps/core-subscriptions/views/controller/register.html.php
 * (the native gateway-selection/checkout page - the same page hooks/
 * subscribe.component_controller_register__1.php overrides the Buy Out
 * charge amount on), right after the "Free Package" / "Change Package"
 * buttons that only render when $bIsRegister is true (the combined
 * signup+package-selection flow, RegisterController::process()'s own
 * req2=register branch). Official phpFox extension point - {plugin
 * call='subscribe.template_controller_register__1'} in the native
 * template, backed by the same filesystem-scanned hooks/ mechanism as
 * every other hook in this app. No core file touched to remove this.
 *
 * "Free Package" (#subscription_change_free) posts back with a blank
 * href="#" and no page-specific handler wired up on the Hulahoot side of
 * this flow - every Hulahoot purchase (paid or free) is created and
 * completed through Service\PurchaseFlow before a buyer ever reaches
 * this page, so there is never a genuine "switch to the free package
 * from here" action for this button to perform for a Hulahoot buyer.
 * Confirmed dead on arrival, not merely redundant - hidden via CSS
 * rather than deleted from the native template.
 */
if (!defined('PHPFOX_INSTALLER')) {
    echo '<style>#subscription_change_free { display: none !important; }</style>';
}
