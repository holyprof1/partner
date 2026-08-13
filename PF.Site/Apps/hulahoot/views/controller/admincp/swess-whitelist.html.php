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
    .hulahoot-admin-actions { white-space: nowrap; text-align: right; }
    .hulahoot-admin-actions .btn { margin-left: 4px; }
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 28px 10px !important; }
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
        <h1><span class="hulahoot-swess-mark">SW</span>{_p var='hulahoot_admin_swess_whitelist'}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/swess/whitelist/add" class="btn btn-primary">
                <i class="fa fa-plus"></i> {_p var='hulahoot_add_swess_whitelist'}
            </a>
        </div>
    </div>

    <table class="table table-bordered table-striped hulahoot-admin-table">
        <thead>
            <tr>
                <th>{_p var='hulahoot_field_user'}</th>
                <th>{_p var='hulahoot_swess_field_enabled'}</th>
                <th>{_p var='hulahoot_swess_field_post_as_self'}</th>
                <th>{_p var='hulahoot_swess_field_post_as_business'}</th>
                <th>{_p var='hulahoot_field_actions'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$entries item=aEntry}
                <tr class="checkRow">
                    <td>{$aEntry.user_name} <span class="text-muted">({$aEntry.full_name})</span></td>
                    <td>{if $aEntry.is_enabled}<span class="label label-success">{_p var='hulahoot_active'}</span>{else}<span class="label label-default">{_p var='hulahoot_inactive'}</span>{/if}</td>
                    <td>{if $aEntry.post_as_self}{_p var='hulahoot_yes'}{else}{_p var='hulahoot_no'}{/if}</td>
                    <td>{if $aEntry.post_as_business}{_p var='hulahoot_yes'}{else}{_p var='hulahoot_no'}{/if}</td>
                    <td class="hulahoot-admin-actions">
                        <a href="/admincp/hulahoot/swess/whitelist/add?id={$aEntry.whitelist_id}" class="btn btn-default btn-sm">{_p var='hulahoot_edit'}</a>
                    </td>
                </tr>
            {foreachelse}
                <tr>
                    <td colspan="5" class="hulahoot-admin-empty">{_p var='hulahoot_no_swess_entries_found'}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
