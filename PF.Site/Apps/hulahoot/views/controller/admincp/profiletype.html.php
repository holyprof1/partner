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
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{_p var='hulahoot_admin_profile_types'}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/profiletype/add" class="btn btn-primary">
                <i class="fa fa-plus"></i> {_p var='hulahoot_add_profile_type'}
            </a>
        </div>
    </div>

    <table class="table table-bordered table-striped hulahoot-admin-table" id="js_drag_drop" data-app="hulahoot" data-action-type="init" data-action="init_drag" data-table="#js_drag_drop" data-ajax="hulahoot.typeOrdering">
        <thead>
            <tr>
                <th class="w40"></th>
                <th>{_p var='hulahoot_field_name'}</th>
                <th>{_p var='hulahoot_field_active'}</th>
                <th>{_p var='hulahoot_field_registration_allowed'}</th>
                <th>{_p var='hulahoot_field_profile_count'}</th>
                <th>{_p var='hulahoot_field_actions'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$types item=aType}
                <tr class="checkRow">
                    <td class="drag_handle text-center">
                        <input type="hidden" name="val[ordering][{$aType.profile_type_id}]" value="{$aType.ordering}" />
                    </td>
                    <td>
                        {_p var=$aType.name}
                        {if $aType.is_default}<span class="label label-info">{_p var='hulahoot_default'}</span>{/if}
                    </td>
                    <td>{if $aType.is_active}<span class="label label-success">{_p var='hulahoot_active'}</span>{else}<span class="label label-default">{_p var='hulahoot_inactive'}</span>{/if}</td>
                    <td>{if $aType.is_individual}{_p var='hulahoot_yes'}{else}{_p var='hulahoot_no'}{/if}</td>
                    <td>{$aType.profile_count}</td>
                    <td class="hulahoot-admin-actions">
                        <a href="/admincp/hulahoot/profiletype/add?id={$aType.profile_type_id}" class="btn btn-default btn-sm">{_p var='hulahoot_edit'}</a>
                        <a href="/admincp/hulahoot/profilecategory?profile_type_id={$aType.profile_type_id}" class="btn btn-default btn-sm">{_p var='hulahoot_manage_categories'}</a>
                        <a href="/admincp/hulahoot/profiletype/delete?id={$aType.profile_type_id}" class="btn btn-danger btn-sm">{_p var='hulahoot_delete'}</a>
                    </td>
                </tr>
            {foreachelse}
                <tr>
                    <td colspan="6" class="hulahoot-admin-empty">{_p var='hulahoot_no_profile_types_found'}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
    <p class="help-block">{_p var='hulahoot_drag_to_reorder_help'}</p>
</div>
