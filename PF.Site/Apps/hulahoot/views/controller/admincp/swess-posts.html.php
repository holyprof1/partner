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
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 40px 10px; border: 1px solid #e2e2e2; border-radius: 4px; background: #fff; }
    .hulahoot-swess-mark {
        display: inline-flex; align-items: center; justify-content: center;
        width: 26px; height: 26px; border-radius: 7px; background: #000; color: #fff;
        font-size: 11px; font-weight: 800; letter-spacing: -.02em; margin-right: 8px;
        vertical-align: middle;
    }
    .hulahoot-swess-filter { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 18px; }
    .hulahoot-swess-filter a {
        font-size: 12px; padding: 5px 12px; border-radius: 999px; text-decoration: none;
        background: #f2f2f2; color: #444; border: 1px solid transparent; transition: all .15s ease;
    }
    .hulahoot-swess-filter a:hover { background: #e6e6e6; }
    .hulahoot-swess-filter a.is-active { background: #000; color: #fff; }
    .hulahoot-swess-filter a .n { opacity: .6; margin-left: 4px; }
    .hulahoot-swess-count-note { font-size: 12px; color: #999; margin-bottom: 12px; }
    table.hulahoot-posts-table { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; }
    table.hulahoot-posts-table th {
        text-align: left; font-size: 11px; text-transform: uppercase; color: #9a9a9a;
        padding: 8px; border-bottom: 1px solid #e5e5e5; white-space: nowrap;
    }
    table.hulahoot-posts-table td { padding: 8px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
    table.hulahoot-posts-table tr:hover td { background: #fafafa; }
    .hulahoot-status-pill {
        display: inline-block; font-size: 11px; border-radius: 999px; padding: 2px 9px;
        background: #f2f2f2; color: #555; white-space: nowrap;
    }
    .hulahoot-status-pill.s-published { background: #000; color: #fff; }
    .hulahoot-status-pill.s-scheduled { background: #d9edf7; color: #245269; }
    .hulahoot-status-pill.s-pending { background: #fcf8e3; color: #7a6320; }
    .hulahoot-status-pill.s-rejected { background: #f8d7da; color: #842029; }
    .hulahoot-status-pill.s-failed { background: #f8d7da; color: #842029; }
    .hulahoot-posts-preview { color: #555; max-width: 340px; }
    .hulahoot-posts-muted { color: #aaa; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1><span class="hulahoot-swess-mark">SW</span>{_p var='hulahoot_admin_swess_posts'}</h1>
    </div>

    <p class="help-block">{_p var='hulahoot_admin_swess_posts_help'}</p>

    <div class="hulahoot-swess-filter">
        <a href="/admincp/hulahoot/swess/posts" class="{if $active_status == ''}is-active{/if}">{_p var='hulahoot_swess_filter_all'}</a>
        {foreach from=$statuses item=sStatus}
            <a href="/admincp/hulahoot/swess/posts?status={$sStatus}" class="{if $active_status == $sStatus}is-active{/if}">
                {$sStatus}<span class="n">{$counts[$sStatus]}</span>
            </a>
        {/foreach}
    </div>

    {if $posts}
        <div class="hulahoot-swess-count-note">{$total_shown} {_p var='hulahoot_swess_posts_showing'}</div>
        <table class="hulahoot-posts-table">
            <thead>
                <tr>
                    <th>{_p var='hulahoot_field_id'}</th>
                    <th>{_p var='hulahoot_field_user'}</th>
                    <th>{_p var='hulahoot_swess_field_content'}</th>
                    <th>{_p var='hulahoot_field_status'}</th>
                    <th>{_p var='hulahoot_swess_field_tag'}</th>
                    <th>{_p var='hulahoot_swess_field_schedule'}</th>
                    <th>{_p var='hulahoot_swess_credit_label'}</th>
                    <th>{_p var='hulahoot_field_updated'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$posts item=aPost}
                    <tr>
                        <td>#{$aPost.swess_post_id}</td>
                        <td>{$aPost.user_name|clean}</td>
                        <td class="hulahoot-posts-preview">{$aPost.content_preview|clean}</td>
                        <td><span class="hulahoot-status-pill s-{$aPost.status}">{$aPost.status}</span></td>
                        <td>
                            {if $aPost.tag_name}
                                {$aPost.tag_name|clean}
                            {else}
                                <span class="hulahoot-posts-muted">&mdash;</span>
                            {/if}
                        </td>
                        <td>
                            {if $aPost.scheduled_display}
                                {$aPost.scheduled_display}
                            {else}
                                <span class="hulahoot-posts-muted">&mdash;</span>
                            {/if}
                        </td>
                        <td>
                            {if $aPost.credit_amount}
                                {$aPost.credit_amount}
                            {else}
                                <span class="hulahoot-posts-muted">&mdash;</span>
                            {/if}
                        </td>
                        <td>{$aPost.updated_display}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {else}
        <div class="hulahoot-admin-empty">{_p var='hulahoot_swess_no_posts_found'}</div>
    {/if}
</div>
