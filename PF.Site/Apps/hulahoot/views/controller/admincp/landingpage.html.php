<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<style>
{literal}
    .hulahoot-landingpage-page { max-width: 100%; }
    .hulahoot-landingpage-page .page-header.hulahoot-page-header {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 10px; border-bottom: 1px solid #e5e5e5;
        margin: 0 0 14px; padding-bottom: 14px;
    }
    .hulahoot-landingpage-page .page-header.hulahoot-page-header h1 { margin: 0; font-size: 20px; font-weight: 600; }
    .hulahoot-landingpage-hint { color: #767676; font-size: 13px; margin: 0 0 14px; }
    .hulahoot-landingpage-textarea {
        width: 100%;
        height: 75vh;
        min-height: 500px;
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        font-size: 13px;
        line-height: 1.5;
        tab-size: 2;
        white-space: pre;
        resize: vertical;
    }
    .hulahoot-landingpage-actions { margin-top: 14px; }
{/literal}
</style>
<div class="hulahoot-landingpage-page">
    <div class="page-header hulahoot-page-header">
        <h1>{_p var='hulahoot_admin_landing_page'}</h1>
    </div>

    <p class="hulahoot-landingpage-hint">{_p var='hulahoot_landing_page_hint'}</p>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <form method="post" action="/admincp/hulahoot/landingpage">
        <input type="hidden" name="hulahoot_token" value="{$csrf_token}">

        <textarea name="html" class="form-control hulahoot-landingpage-textarea" spellcheck="false">{$html|clean}</textarea>

        <div class="hulahoot-landingpage-actions">
            <button type="submit" class="btn btn-primary">{_p var='hulahoot_save_changes'}</button>
        </div>
    </form>
</div>
