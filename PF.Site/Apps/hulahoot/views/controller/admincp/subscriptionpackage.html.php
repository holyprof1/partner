<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<style>
{literal}
    .hulahoot-admin { max-width: 900px; }
    .hulahoot-admin .page-header.hulahoot-page-header {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 10px; border-bottom: 1px solid #e5e5e5;
        margin: 0 0 20px; padding-bottom: 14px;
    }
    .hulahoot-admin .page-header.hulahoot-page-header h1 { margin: 0; font-size: 20px; font-weight: 600; }
    .hulahoot-admin .page-header.hulahoot-page-header .hulahoot-header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .hulahoot-admin-intro { color: #666; margin: -8px 0 18px; }
    .hulahoot-admin-table { background: #fff; }
    .hulahoot-admin-table th {
        font-size: 12px; text-transform: uppercase; letter-spacing: .03em;
        color: #767676; font-weight: 600; border-bottom-width: 1px !important;
    }
    .hulahoot-admin-table td { vertical-align: middle !important; }
    .hulahoot-admin-actions { white-space: nowrap; text-align: right; }
    .hulahoot-admin-actions .btn { margin-left: 4px; }
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 28px 10px !important; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{_p var='hulahoot_admin_subscription_packages'}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/subscribe/add" class="btn btn-default">{_p var='hulahoot_manage_native_packages'}</a>
        </div>
    </div>

    {if !$subscriptions_active}
        <div class="alert alert-warning">{_p var='hulahoot_subscriptions_app_inactive'}</div>
    {/if}

    <p class="hulahoot-admin-intro">{_p var='hulahoot_subscription_packages_intro'}</p>

    <table class="table table-bordered table-striped hulahoot-admin-table">
        <thead>
            <tr>
                <th>{_p var='hulahoot_field_name'}</th>
                <th>{_p var='hulahoot_field_native_price'}</th>
                <th>{_p var='hulahoot_field_native_status'}</th>
                <th>{_p var='hulahoot_field_hulahoot_rules'}</th>
                <th>{_p var='hulahoot_field_industries'}</th>
                <th>{_p var='hulahoot_field_actions'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$packages item=aPackage}
                <tr>
                    <td>{_p var=$aPackage.title}</td>
                    <td>
                        {if $aPackage.default_cost}
                            {$aPackage.default_cost} {$aPackage.default_currency_id|clean}
                        {else}
                            {_p var='hulahoot_free'}
                        {/if}
                    </td>
                    <td>{if $aPackage.is_active}<span class="label label-success">{_p var='hulahoot_active'}</span>{else}<span class="label label-default">{_p var='hulahoot_inactive'}</span>{/if}</td>
                    <td>
                        {if $aPackage.hulahoot_rules && $aPackage.hulahoot_rules.is_active}
                            <span class="label label-success">{_p var='hulahoot_configured'}</span>
                        {elseif $aPackage.hulahoot_rules}
                            <span class="label label-default">{_p var='hulahoot_rules_inactive'}</span>
                        {else}
                            <span class="label label-warning">{_p var='hulahoot_not_configured'}</span>
                        {/if}
                    </td>
                    <td>
                        {if $aPackage.hulahoot_industry_count}
                            {$aPackage.hulahoot_industry_count}
                        {else}
                            {_p var='hulahoot_all_industries'}
                        {/if}
                    </td>
                    <td class="hulahoot-admin-actions">
                        <a href="/admincp/hulahoot/subscriptionpackage/edit?id={$aPackage.package_id}" class="btn btn-default btn-sm">{_p var='hulahoot_edit_rules'}</a>
                    </td>
                </tr>
            {foreachelse}
                <tr>
                    <td colspan="6" class="hulahoot-admin-empty">{_p var='hulahoot_no_subscription_packages_found'}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
