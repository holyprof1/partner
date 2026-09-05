<?php
defined('PHPFOX') or exit('NO DICE!');
?>
<style>
{literal}
    .hulahoot-admin { max-width: 700px; }
    .hulahoot-admin .page-header.hulahoot-page-header {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 10px; border-bottom: 1px solid #e5e5e5;
        margin: 0 0 20px; padding-bottom: 14px;
    }
    .hulahoot-admin .page-header.hulahoot-page-header h1 { margin: 0; font-size: 20px; font-weight: 600; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{_p var='hulahoot_delete_category'}</h1>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <p>{_p var='hulahoot_confirm_delete_category' name=$category_name}</p>

    <form method="post" action="/admincp/hulahoot/profilecategory/delete?id={$category.category_id}">
        <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
        <button type="submit" class="btn btn-danger">{_p var='hulahoot_delete'}</button>
        <a href="/admincp/hulahoot/profilecategory?profile_type_id={$category.profile_type_id}" class="btn btn-link">{_p var='hulahoot_cancel'}</a>
    </form>
</div>
