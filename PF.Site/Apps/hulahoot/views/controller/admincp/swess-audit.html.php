<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<style>
{literal}
    .hulahoot-admin { max-width: 1100px; }
    .hulahoot-admin .page-header.hulahoot-page-header {
        border-bottom: 1px solid #e5e5e5; margin: 0 0 20px; padding-bottom: 14px;
    }
    .hulahoot-admin .page-header.hulahoot-page-header h1 { margin: 0; font-size: 20px; font-weight: 600; }
    .hulahoot-admin-table { background: #fff; }
    .hulahoot-admin-table th {
        font-size: 12px; text-transform: uppercase; letter-spacing: .03em;
        color: #767676; font-weight: 600; border-bottom-width: 1px !important;
    }
    .hulahoot-admin-table td { vertical-align: middle !important; font-size: 13px; }
    .hulahoot-admin-table tbody tr { transition: background-color .1s ease; }
    .hulahoot-admin-table.table-striped tbody tr:hover > td { background-color: #f3f3f3 !important; }
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 28px 10px !important; }
    .hulahoot-swess-mark {
        display: inline-flex; align-items: center; justify-content: center;
        width: 26px; height: 26px; border-radius: 7px; background: #000; color: #fff;
        font-size: 11px; font-weight: 800; letter-spacing: -.02em; margin-right: 8px;
        vertical-align: middle;
    }
    .hulahoot-swess-context { color: #888; font-size: 11.5px; word-break: break-all; }
    /* Positive/allowed outcomes green, negative/blocked outcomes red,
       everything else (routine lifecycle bookkeeping) neutral gray - the
       original two-action set (post_check_allowed/denied) grew to a dozen
       real action names once the post lifecycle shipped, so this widens
       the same allowed/denied convention to match rather than leaving new
       actions all falling back to one generic gray label. */
    .label-allowed { background: #2e7d32; }
    .label-denied { background: #c62828; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1><span class="hulahoot-swess-mark">SW</span>{_p var='hulahoot_admin_swess_audit'}</h1>
    </div>

    <table class="table table-bordered table-striped hulahoot-admin-table">
        <thead>
            <tr>
                <th>{_p var='hulahoot_field_date'}</th>
                <th>{_p var='hulahoot_field_user'}</th>
                <th>{_p var='hulahoot_swess_field_identity'}</th>
                <th>{_p var='hulahoot_swess_field_action'}</th>
                <th>{_p var='hulahoot_swess_field_context'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$log item=aRow}
                <tr>
                    <td>{$aRow.created_display}</td>
                    <td>{$aRow.user_name}</td>
                    <td>{if $aRow.identity_type}{$aRow.identity_type}{if $aRow.identity_id} #{$aRow.identity_id}{/if}{else}&mdash;{/if}</td>
                    <td>
                        {if $aRow.action == 'post_check_allowed' || $aRow.action == 'whitelist_enabled' || $aRow.action == 'identity_approved' || $aRow.action == 'post_approved' || $aRow.action == 'post_published'}
                            <span class="label label-allowed">{$aRow.action}</span>
                        {elseif $aRow.action == 'post_check_denied' || $aRow.action == 'whitelist_disabled' || $aRow.action == 'identity_revoked' || $aRow.action == 'post_rejected' || $aRow.action == 'post_failed' || $aRow.action == 'post_cancelled'}
                            <span class="label label-denied">{$aRow.action}</span>
                        {else}
                            <span class="label label-default">{$aRow.action}</span>
                        {/if}
                    </td>
                    <td class="hulahoot-swess-context">{$aRow.context}</td>
                </tr>
            {foreachelse}
                <tr>
                    <td colspan="5" class="hulahoot-admin-empty">{_p var='hulahoot_swess_no_audit_entries'}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
