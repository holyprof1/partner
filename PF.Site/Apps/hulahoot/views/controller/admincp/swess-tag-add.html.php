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
    .hulahoot-admin-form .form-group { margin-bottom: 16px; }
    .hulahoot-admin-form label.control-label { font-weight: 600; }
    .hulahoot-admin-form .help-block { margin-top: 4px; font-size: 12.5px; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{if $is_edit}{_p var='hulahoot_edit_swess_tag'}{else}{_p var='hulahoot_add_swess_tag'}{/if}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/swess/tag" class="btn btn-default">{_p var='hulahoot_back_to_swess_tags'}</a>
        </div>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <form method="post" action="/admincp/hulahoot/swess/tag/add{if $is_edit}?id={$tag_id}{/if}" class="form-horizontal hulahoot-admin-form">
        <input type="hidden" name="hulahoot_token" value="{$csrf_token}">

        <div class="form-group">
            <label for="hulahoot_swess_tag_name" class="col-sm-3 control-label">{_p var='hulahoot_field_name'}</label>
            <div class="col-sm-9">
                <input type="text" name="name" id="hulahoot_swess_tag_name" class="form-control" maxlength="100" value="{$values.name|clean}" placeholder="{_p var='hulahoot_swess_tag_name_placeholder'}" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label for="hulahoot_swess_tag_description" class="col-sm-3 control-label">{_p var='hulahoot_field_description'}</label>
            <div class="col-sm-9">
                <textarea name="description" id="hulahoot_swess_tag_description" class="form-control" maxlength="255" rows="2">{$values.description|clean}</textarea>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_field_active'}</label>
            <div class="col-sm-9">
                <label class="checkbox-inline">
                    <input type="checkbox" name="is_active" value="1" {if $values.is_active}checked{/if}> {_p var='hulahoot_active'}
                </label>
            </div>
        </div>

        <div class="form-group">
            <label for="hulahoot_swess_tag_ordering" class="col-sm-3 control-label">{_p var='hulahoot_field_sort_order'}</label>
            <div class="col-sm-9">
                <input type="number" name="ordering" id="hulahoot_swess_tag_ordering" class="form-control" value="{$values.ordering}">
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
                <button type="submit" class="btn btn-primary">{_p var='hulahoot_save_changes'}</button>
                <a href="/admincp/hulahoot/swess/tag" class="btn btn-link">{_p var='hulahoot_cancel'}</a>
            </div>
        </div>
    </form>
</div>
