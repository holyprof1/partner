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
    .hulahoot-current-image {
        display: block; max-width: 240px; max-height: 120px; margin-bottom: 8px;
        border-radius: 4px; border: 1px solid #e2e2e2;
    }
    .hulahoot-icon-preview-row { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .hulahoot-icon-preview {
        width: 40px; height: 40px; border-radius: 50%; background: #f3f3f3;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; color: #333; flex-shrink: 0;
    }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{if $is_edit}{_p var='hulahoot_edit_industry'}{else}{_p var='hulahoot_add_industry'}{/if}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/industry" class="btn btn-default">{_p var='hulahoot_back_to_industries'}</a>
        </div>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <form method="post" action="/admincp/hulahoot/industry/add{if $is_edit}?id={$industry_id}{/if}" enctype="multipart/form-data" class="form-horizontal hulahoot-admin-form">
        <input type="hidden" name="hulahoot_token" value="{$csrf_token}">

        <div class="form-group">
            <label for="hulahoot_industry_name" class="col-sm-3 control-label">{_p var='hulahoot_field_name'}</label>
            <div class="col-sm-9">
                <input type="text" name="name" id="hulahoot_industry_name" class="form-control" maxlength="100" value="{_p var=$values.name}" placeholder="{_p var='hulahoot_industry_name_placeholder'}" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label for="hulahoot_industry_slug" class="col-sm-3 control-label">{_p var='hulahoot_field_slug'}</label>
            <div class="col-sm-9">
                <input type="text" name="slug" id="hulahoot_industry_slug" class="form-control" maxlength="100" value="{$values.slug|clean}" placeholder="{_p var='hulahoot_slug_placeholder'}">
                <span class="help-block">{_p var='hulahoot_industry_slug_help'}</span>
            </div>
        </div>

        <div class="form-group">
            <label for="hulahoot_industry_description" class="col-sm-3 control-label">{_p var='hulahoot_field_description'}</label>
            <div class="col-sm-9">
                <textarea name="description" id="hulahoot_industry_description" class="form-control" rows="3">{$values.description|clean}</textarea>
            </div>
        </div>

        <div class="form-group">
            <label for="hulahoot_industry_banner" class="col-sm-3 control-label">{_p var='hulahoot_field_banner'}</label>
            <div class="col-sm-9">
                {if $banner_url}
                    <img class="hulahoot-current-image" src="{$banner_url}" alt="">
                {/if}
                <input type="file" name="banner" id="hulahoot_industry_banner" accept="image/jpeg,image/png,image/gif">
            </div>
        </div>

        <div class="form-group">
            <label for="hulahoot_industry_thumbnail" class="col-sm-3 control-label">{_p var='hulahoot_field_thumbnail'}</label>
            <div class="col-sm-9">
                {if $thumbnail_url}
                    <img class="hulahoot-current-image" src="{$thumbnail_url}" alt="">
                {/if}
                <input type="file" name="thumbnail" id="hulahoot_industry_thumbnail" accept="image/jpeg,image/png,image/gif">
            </div>
        </div>

        <div class="form-group">
            <label for="hulahoot_industry_icon" class="col-sm-3 control-label">{_p var='hulahoot_field_icon'}</label>
            <div class="col-sm-9">
                <input type="text" name="icon" id="hulahoot_industry_icon" class="form-control" maxlength="100" value="{$values.icon|clean}" placeholder="fa-car">
                <div class="hulahoot-icon-preview-row">
                    <span class="hulahoot-icon-preview"><i id="js_hulahoot_icon_preview" class="fa {$values.icon|clean}"></i></span>
                    <span class="help-block" style="margin:0;">
                        {_p var='hulahoot_icon_help'}
                        <a href="https://fontawesome.com/v4/icons/" target="_blank" rel="noopener">{_p var='hulahoot_icon_help_link'}</a>
                    </span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="hulahoot_industry_sort_order" class="col-sm-3 control-label">{_p var='hulahoot_field_sort_order'}</label>
            <div class="col-sm-9">
                <input type="number" name="sort_order" id="hulahoot_industry_sort_order" class="form-control" value="{$values.sort_order}">
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
            <div class="col-sm-offset-3 col-sm-9">
                <button type="submit" class="btn btn-primary">{_p var='hulahoot_save_changes'}</button>
                <a href="/admincp/hulahoot/industry" class="btn btn-link">{_p var='hulahoot_cancel'}</a>
            </div>
        </div>
    </form>
</div>
<script>
{literal}
(function () {
    var input = document.getElementById('hulahoot_industry_icon');
    var preview = document.getElementById('js_hulahoot_icon_preview');
    if (!input || !preview) {
        return;
    }
    input.addEventListener('input', function () {
        preview.className = 'fa ' + input.value.trim();
    });
})();
{/literal}
</script>
