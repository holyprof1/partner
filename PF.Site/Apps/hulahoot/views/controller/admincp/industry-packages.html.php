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
    .hulahoot-admin-table { background: #fff; }
    .hulahoot-admin-table th {
        font-size: 12px; text-transform: uppercase; letter-spacing: .03em;
        color: #767676; font-weight: 600; border-bottom-width: 1px !important;
    }
    .hulahoot-admin-table td { vertical-align: middle !important; }
    .hulahoot-admin-actions { white-space: nowrap; text-align: right; }
    .hulahoot-admin-actions .btn, .hulahoot-admin-actions form { margin-left: 4px; display: inline-block; }
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 28px 10px !important; }
    .hulahoot-assign-section {
        margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5;
    }
    .hulahoot-assign-section h2 { font-size: 15px; font-weight: 700; margin: 0 0 12px; }
    .hulahoot-assign-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .hulahoot-assign-form select { max-width: 360px; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{_p var='hulahoot_industry_packages_title' name=_p($industry.name)}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/industry" class="btn btn-default">{_p var='hulahoot_back_to_industries'}</a>
            <a href="/admincp/subscribe/add" class="btn btn-primary">{_p var='hulahoot_create_new_package'}</a>
        </div>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <table class="table table-bordered table-striped hulahoot-admin-table">
        <thead>
            <tr>
                <th>{_p var='hulahoot_field_name'}</th>
                <th>{_p var='hulahoot_field_native_price'}</th>
                <th>{_p var='hulahoot_field_native_status'}</th>
                <th>{_p var='hulahoot_field_hulahoot_rules'}</th>
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
                        {else}
                            <span class="label label-warning">{_p var='hulahoot_not_configured'}</span>
                        {/if}
                    </td>
                    <td class="hulahoot-admin-actions">
                        <a href="/admincp/hulahoot/subscriptionpackage/edit?id={$aPackage.package_id}" class="btn btn-default btn-sm">{_p var='hulahoot_edit_rules'}</a>
                        <form method="post" action="/admincp/hulahoot/industry/packages?id={$industry.industry_id}" onsubmit="return confirm('{_p var='hulahoot_confirm_unassign_package' phpfox_squote=true}');">
                            <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
                            <input type="hidden" name="remove_package_id" value="{$aPackage.package_id}">
                            <button type="submit" class="btn btn-danger btn-sm">{_p var='hulahoot_unassign'}</button>
                        </form>
                    </td>
                </tr>
            {foreachelse}
                <tr>
                    <td colspan="5" class="hulahoot-admin-empty">{_p var='hulahoot_no_packages_assigned'}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>

    <div class="hulahoot-assign-section">
        <h2>{_p var='hulahoot_create_from_template'}</h2>
        {if !count($templates)}
            <p class="form-control-static">{_p var='hulahoot_no_templates'} <a href="/admincp/hulahoot/packagetemplate">{_p var='hulahoot_manage_default_packages'}</a></p>
        {else}
            <form method="post" action="/admincp/hulahoot/industry/packages?id={$industry.industry_id}" class="hulahoot-assign-form">
                <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
                <select name="create_from_template_id" class="form-control" required>
                    <option value="">{_p var='hulahoot_select_a_template'}</option>
                    {foreach from=$templates item=aTemplate}
                        <option value="{$aTemplate.template_id}">{$aTemplate.name|clean} &mdash; {$aTemplate.default_cost|clean} USD</option>
                    {/foreach}
                </select>
                <button type="submit" class="btn btn-primary">{_p var='hulahoot_create'}</button>
            </form>
            <span class="help-block">{_p var='hulahoot_create_from_template_help' name=_p($industry.name)}</span>
        {/if}
    </div>

    <div class="hulahoot-assign-section">
        <h2>{_p var='hulahoot_assign_existing_package'}</h2>
        {if !count($unassigned_packages)}
            <p class="form-control-static">{_p var='hulahoot_no_unassigned_packages'}</p>
        {else}
            <form method="post" action="/admincp/hulahoot/industry/packages?id={$industry.industry_id}" class="hulahoot-assign-form">
                <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
                <select name="assign_package_id" class="form-control" required>
                    <option value="">{_p var='hulahoot_select_a_package'}</option>
                    {foreach from=$unassigned_packages item=aPackage}
                        <option value="{$aPackage.package_id}">{_p var=$aPackage.title}</option>
                    {/foreach}
                </select>
                <button type="submit" class="btn btn-primary">{_p var='hulahoot_assign'}</button>
            </form>
        {/if}
    </div>
</div>
