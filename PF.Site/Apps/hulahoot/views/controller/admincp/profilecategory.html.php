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
    .hulahoot-admin-table .hulahoot-sub-row td:first-child { color: #555; }
    .hulahoot-admin-actions { white-space: nowrap; text-align: right; }
    .hulahoot-admin-actions .btn { margin-left: 4px; }
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 28px 10px !important; }
{/literal}
</style>
{if empty($profile_type)}
    <div class="hulahoot-admin">
        <div class="page-header hulahoot-page-header">
            <h1>{_p var='hulahoot_admin_profile_categories'}</h1>
        </div>

        <p>{_p var='hulahoot_pick_a_profile_type'}</p>

        <ul class="list-group hulahoot-type-picker">
            {foreach from=$types item=aType}
                <li class="list-group-item">
                    <a href="/admincp/hulahoot/profilecategory?profile_type_id={$aType.profile_type_id}">
                        {_p var=$aType.name}
                    </a>
                </li>
            {/foreach}
        </ul>
    </div>
{else}
    <div class="hulahoot-admin">
        <div class="page-header hulahoot-page-header">
            <h1>{_p var='hulahoot_admin_profile_categories'} &mdash; {_p var=$profile_type.name}</h1>
            <div class="hulahoot-header-actions">
                <a href="/admincp/hulahoot/profilecategory" class="btn btn-default">{_p var='hulahoot_change_profile_type'}</a>
                <a href="/admincp/hulahoot/profilecategory/add?profile_type_id={$profile_type.profile_type_id}" class="btn btn-primary">
                    <i class="fa fa-plus"></i> {_p var='hulahoot_add_category'}
                </a>
            </div>
        </div>

        <table class="table table-bordered table-striped hulahoot-admin-table" id="js_drag_drop" data-app="hulahoot" data-action-type="init" data-action="init_drag" data-table="#js_drag_drop" data-ajax="hulahoot.categoryOrdering">
            <thead>
                <tr>
                    <th class="w40"></th>
                    <th>{_p var='hulahoot_field_name'}</th>
                    <th>{_p var='hulahoot_field_active'}</th>
                    <th>{_p var='hulahoot_field_profile_count'}</th>
                    <th>{_p var='hulahoot_field_actions'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$categories item=aCategory}
                    {if $aCategory.parent_id == 0}
                        <tr class="checkRow">
                            <td class="drag_handle text-center">
                                <input type="hidden" name="val[ordering][{$aCategory.category_id}]" value="{$aCategory.ordering}" />
                            </td>
                            <td><strong>{_p var=$aCategory.name}</strong></td>
                            <td>{if $aCategory.is_active}<span class="label label-success">{_p var='hulahoot_active'}</span>{else}<span class="label label-default">{_p var='hulahoot_inactive'}</span>{/if}</td>
                            <td>{$aCategory.profile_count}</td>
                            <td class="hulahoot-admin-actions">
                                <a href="/admincp/hulahoot/profilecategory/add?id={$aCategory.category_id}" class="btn btn-default btn-sm">{_p var='hulahoot_edit'}</a>
                                <a href="/admincp/hulahoot/profilecategory/add?profile_type_id={$profile_type.profile_type_id}&parent_id={$aCategory.category_id}" class="btn btn-default btn-sm">{_p var='hulahoot_add_subcategory'}</a>
                                <a href="/admincp/hulahoot/profilecategory/delete?id={$aCategory.category_id}" class="btn btn-danger btn-sm">{_p var='hulahoot_delete'}</a>
                            </td>
                        </tr>
                        {foreach from=$categories item=aSub}
                            {if $aSub.parent_id == $aCategory.category_id}
                                <tr class="hulahoot-sub-row">
                                    <td></td>
                                    <td>&nbsp;&nbsp;&nbsp;&nbsp;&raquo; {_p var=$aSub.name}</td>
                                    <td>{if $aSub.is_active}<span class="label label-success">{_p var='hulahoot_active'}</span>{else}<span class="label label-default">{_p var='hulahoot_inactive'}</span>{/if}</td>
                                    <td>{$aSub.profile_count}</td>
                                    <td class="hulahoot-admin-actions">
                                        <a href="/admincp/hulahoot/profilecategory/add?id={$aSub.category_id}" class="btn btn-default btn-sm">{_p var='hulahoot_edit'}</a>
                                        <a href="/admincp/hulahoot/profilecategory/delete?id={$aSub.category_id}" class="btn btn-danger btn-sm">{_p var='hulahoot_delete'}</a>
                                    </td>
                                </tr>
                            {/if}
                        {/foreach}
                    {/if}
                {foreachelse}
                    <tr>
                        <td colspan="5" class="hulahoot-admin-empty">{_p var='hulahoot_no_categories_found'}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
        <p class="help-block">{_p var='hulahoot_drag_to_reorder_help'}</p>
    </div>
{/if}
