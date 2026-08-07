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
    .hulahoot-admin-actions .btn { margin-left: 4px; }
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 28px 10px !important; }
    .hulahoot-industry-thumb {
        width: 40px; height: 40px; border-radius: 4px; object-fit: cover;
        background: #f0f0f0; display: inline-block; vertical-align: middle;
    }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{_p var='hulahoot_admin_industries'}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/industry/add" class="btn btn-primary">
                <i class="fa fa-plus"></i> {_p var='hulahoot_add_industry'}
            </a>
        </div>
    </div>

    <table class="table table-bordered table-striped hulahoot-admin-table" id="js_drag_drop" data-app="hulahoot" data-action-type="init" data-action="init_drag" data-table="#js_drag_drop" data-ajax="hulahoot.industryOrdering">
        <thead>
            <tr>
                <th class="w40"></th>
                <th></th>
                <th>{_p var='hulahoot_field_name'}</th>
                <th>{_p var='hulahoot_field_active'}</th>
                <th>{_p var='hulahoot_field_package_count'}</th>
                <th>{_p var='hulahoot_field_actions'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$industries item=aIndustry}
                <tr class="checkRow">
                    <td class="drag_handle text-center">
                        <input type="hidden" name="val[ordering][{$aIndustry.industry_id}]" value="{$aIndustry.sort_order}" />
                    </td>
                    <td>
                        {if $aIndustry.thumbnail_url}
                            <img class="hulahoot-industry-thumb" src="{$aIndustry.thumbnail_url}" alt="">
                        {/if}
                    </td>
                    <td>{_p var=$aIndustry.name}</td>
                    <td>{if $aIndustry.is_active}<span class="label label-success">{_p var='hulahoot_active'}</span>{else}<span class="label label-default">{_p var='hulahoot_inactive'}</span>{/if}</td>
                    <td><a href="/admincp/hulahoot/industry/packages?id={$aIndustry.industry_id}">{$aIndustry.package_count}</a></td>
                    <td class="hulahoot-admin-actions">
                        <a href="/admincp/hulahoot/industry/packages?id={$aIndustry.industry_id}" class="btn btn-default btn-sm">{_p var='hulahoot_manage_packages'}</a>
                        <a href="/admincp/hulahoot/industry/add?id={$aIndustry.industry_id}" class="btn btn-default btn-sm">{_p var='hulahoot_edit'}</a>
                        <a href="/admincp/hulahoot/industry/delete?id={$aIndustry.industry_id}" class="btn btn-danger btn-sm">{_p var='hulahoot_delete'}</a>
                    </td>
                </tr>
            {foreachelse}
                <tr>
                    <td colspan="6" class="hulahoot-admin-empty">{_p var='hulahoot_no_industries_found_admin'}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
    <p class="help-block">{_p var='hulahoot_drag_to_reorder_help'}</p>
</div>
