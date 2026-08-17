<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<style>
{literal}
    .hulahoot-admin { max-width: 1000px; }
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
    .hulahoot-admin-table tbody tr { transition: background-color .1s ease; }
    .hulahoot-admin-table.table-striped tbody tr:hover > td { background-color: #f3f3f3 !important; }
    .hulahoot-admin-actions { white-space: nowrap; text-align: right; }
    .hulahoot-admin-actions .btn { margin-left: 4px; transition: transform .1s ease; }
    .hulahoot-admin-actions .btn:hover { transform: translateY(-1px); }
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 28px 10px !important; }
    .hulahoot-header-actions .btn { transition: transform .1s ease, box-shadow .15s ease; }
    .hulahoot-header-actions .btn:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0,0,0,.12); }
    .hulahoot-swess-mark {
        display: inline-flex; align-items: center; justify-content: center;
        width: 26px; height: 26px; border-radius: 7px; background: #000; color: #fff;
        font-size: 11px; font-weight: 800; letter-spacing: -.02em; margin-right: 8px;
        vertical-align: middle;
    }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1><span class="hulahoot-swess-mark">SW</span>{_p var='hulahoot_admin_swess_tags'}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/swess/tag/add" class="btn btn-primary">
                <i class="fa fa-plus"></i> {_p var='hulahoot_add_swess_tag'}
            </a>
        </div>
    </div>

    <table class="table table-bordered table-striped hulahoot-admin-table">
        <thead>
            <tr>
                <th>{_p var='hulahoot_field_name'}</th>
                <th>{_p var='hulahoot_field_description'}</th>
                <th>{_p var='hulahoot_field_active'}</th>
                <th>{_p var='hulahoot_field_actions'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$tags item=aTag}
                <tr class="checkRow">
                    <td>{$aTag.name}</td>
                    <td class="text-muted">{$aTag.description}</td>
                    <td>{if $aTag.is_active}<span class="label label-success">{_p var='hulahoot_active'}</span>{else}<span class="label label-default">{_p var='hulahoot_inactive'}</span>{/if}</td>
                    <td class="hulahoot-admin-actions">
                        <a href="/admincp/hulahoot/swess/tag/add?id={$aTag.tag_id}" class="btn btn-default btn-sm">{_p var='hulahoot_edit'}</a>
                    </td>
                </tr>
            {foreachelse}
                <tr>
                    <td colspan="4" class="hulahoot-admin-empty">{_p var='hulahoot_no_swess_tags_found'}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
