<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<style>
{literal}
    .hulahoot-wallet { max-width: 700px; }
    .hulahoot-wallet .panel-heading { font-weight: 600; }
    .hulahoot-wallet-row { margin-bottom: 10px; }
    .hulahoot-wallet-row .label-text { font-weight: 600; display: inline-block; min-width: 140px; }
    .hulahoot-wallet-credits { margin-top: 24px; color: #888; }
{/literal}
</style>
<div class="hulahoot-wallet">
    <div class="panel panel-default">
        <div class="panel-heading">{_p var='hulahoot_swess_wallet'}</div>
        <div class="panel-body">
            {if $subscription.has_plan}
                <div class="hulahoot-wallet-row">
                    <span class="label-text">{_p var='hulahoot_current_plan'}</span>
                    <span>{$subscription.title|clean}</span>
                </div>
                <div class="hulahoot-wallet-row">
                    <span class="label-text">{_p var='hulahoot_subscription_status'}</span>
                    <span>{$subscription.status|clean}</span>
                </div>
                {if $subscription.expiry_date_display}
                    <div class="hulahoot-wallet-row">
                        <span class="label-text">{_p var='hulahoot_expiration_date'}</span>
                        <span>{$subscription.expiry_date_display|clean}</span>
                    </div>
                {/if}
                <a href="/subscribe" class="btn btn-primary btn-sm">{_p var='hulahoot_upgrade_renew'}</a>
            {else}
                <div class="hulahoot-wallet-row">
                    <span class="label-text">{_p var='hulahoot_current_plan'}</span>
                    <span>{_p var='hulahoot_no_active_plan'}</span>
                </div>
                <a href="/subscribe" class="btn btn-primary btn-sm">{_p var='hulahoot_choose_plan'}</a>
            {/if}
        </div>
    </div>

    <div class="hulahoot-wallet-credits">
        <strong>{_p var='hulahoot_credits'}</strong>
        <p>{_p var='hulahoot_credits_placeholder'}</p>
    </div>
</div>
