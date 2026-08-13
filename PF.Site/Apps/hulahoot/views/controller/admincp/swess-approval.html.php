<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<style>
{literal}
    .hulahoot-admin { max-width: 1000px; }
    .hulahoot-admin .page-header.hulahoot-page-header {
        border-bottom: 1px solid #e5e5e5; margin: 0 0 20px; padding-bottom: 14px;
    }
    .hulahoot-admin .page-header.hulahoot-page-header h1 { margin: 0; font-size: 20px; font-weight: 600; }
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 40px 10px; border: 1px solid #e2e2e2; border-radius: 4px; background: #fff; }
    .hulahoot-swess-mark {
        display: inline-flex; align-items: center; justify-content: center;
        width: 26px; height: 26px; border-radius: 7px; background: #000; color: #fff;
        font-size: 11px; font-weight: 800; letter-spacing: -.02em; margin-right: 8px;
        vertical-align: middle;
    }
    .hulahoot-swess-queue-card { border: 1px solid #e2e2e2; border-radius: 4px; padding: 16px 18px; margin-bottom: 14px; background: #fff; }
    .hulahoot-swess-queue-meta { font-size: 12px; color: #888; margin-bottom: 8px; }
    .hulahoot-swess-queue-content { font-size: 14px; margin-bottom: 10px; white-space: pre-wrap; }
    .hulahoot-swess-queue-facts { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
    .hulahoot-swess-queue-facts span { font-size: 11.5px; background: #f2f2f2; border-radius: 999px; padding: 3px 10px; }
    .hulahoot-swess-queue-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start; }
    .hulahoot-swess-queue-actions form { display: flex; gap: 6px; align-items: center; }
    .hulahoot-swess-queue-actions input[type=text] { padding: 4px 8px; width: 260px; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1><span class="hulahoot-swess-mark">SW</span>{_p var='hulahoot_admin_swess_approval'}</h1>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    {foreach from=$pending item=aPost}
        <div class="hulahoot-swess-queue-card">
            <div class="hulahoot-swess-queue-meta">{$aPost.user_name} &middot; {$aPost.created_display}</div>
            <div class="hulahoot-swess-queue-content">{$aPost.content|clean}</div>
            <div class="hulahoot-swess-queue-facts">
                <span>{if $aPost.identity_type == 'self'}{_p var='hulahoot_swess_identity_self'}{else}{_p var='hulahoot_swess_identity_page'} #{$aPost.identity_id}{/if}</span>
                <span>{$aPost.distribution_target_type}{if $aPost.distribution_target_label}: {$aPost.distribution_target_label}{/if}</span>
                {if $aPost.scheduled_display}<span>{_p var='hulahoot_swess_field_scheduled_for'}: {$aPost.scheduled_display}</span>{/if}
            </div>
            <div class="hulahoot-swess-queue-actions">
                <form method="post" action="/admincp/hulahoot/swess/approval">
                    <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
                    <input type="hidden" name="do" value="approve">
                    <input type="hidden" name="swess_post_id" value="{$aPost.swess_post_id}">
                    <button type="submit" class="btn btn-primary btn-sm">{_p var='hulahoot_swess_approve'}</button>
                </form>
                <form method="post" action="/admincp/hulahoot/swess/approval">
                    <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
                    <input type="hidden" name="do" value="reject">
                    <input type="hidden" name="swess_post_id" value="{$aPost.swess_post_id}">
                    <input type="text" name="rejection_reason" placeholder="{_p var='hulahoot_swess_rejection_reason_placeholder'}" required>
                    <button type="submit" class="btn btn-danger btn-sm">{_p var='hulahoot_swess_reject'}</button>
                </form>
            </div>
        </div>
    {foreachelse}
        <div class="hulahoot-admin-empty">{_p var='hulahoot_swess_no_pending_posts'}</div>
    {/foreach}
</div>
