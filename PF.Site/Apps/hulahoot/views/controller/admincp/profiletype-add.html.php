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
    .hulahoot-advanced { margin: 22px 0 20px; border: 1px solid #e2e2e2; border-radius: 4px; background: #fafafa; }
    .hulahoot-advanced > summary { cursor: pointer; padding: 10px 14px; font-weight: 600; font-size: 13px; color: #555; list-style: none; user-select: none; }
    .hulahoot-advanced > summary::-webkit-details-marker { display: none; }
    .hulahoot-advanced > summary::before { content: "\25B8"; display: inline-block; margin-right: 7px; transition: transform .15s ease; }
    .hulahoot-advanced[open] > summary::before { transform: rotate(90deg); }
    .hulahoot-advanced > summary:hover { color: #222; }
    .hulahoot-advanced-body { padding: 4px 16px 16px; border-top: 1px solid #e2e2e2; }
    .hulahoot-advanced-body .form-group:first-child { margin-top: 16px; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{if $is_edit}{_p var='hulahoot_edit_profile_type'}{else}{_p var='hulahoot_add_profile_type'}{/if}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/profiletype" class="btn btn-default">{_p var='hulahoot_back_to_profile_types'}</a>
        </div>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <form method="post" action="/admincp/hulahoot/profiletype/add{if $is_edit}?id={$type_id}{/if}" class="form-horizontal hulahoot-admin-form">
        <input type="hidden" name="hulahoot_token" value="{$csrf_token}">

        <div class="form-group">
            <label for="hulahoot_type_name" class="col-sm-3 control-label">{_p var='hulahoot_field_name'}</label>
            <div class="col-sm-9">
                <input type="text" name="name" id="hulahoot_type_name" class="form-control" maxlength="100" value="{_p var=$values.name}" placeholder="{_p var='hulahoot_type_name_placeholder'}" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_field_active'}</label>
            <div class="col-sm-9">
                <label class="checkbox-inline">
                    <input type="checkbox" name="is_active" value="1" {if $values.is_active}checked{/if}> {_p var='hulahoot_active'}
                </label>
                {if $values.is_default}
                    <span class="help-block">{_p var='hulahoot_default_must_stay_active'}</span>
                {/if}
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_field_registration_allowed'}</label>
            <div class="col-sm-9">
                <label class="checkbox-inline">
                    <input type="checkbox" name="is_individual" value="1" {if $values.is_individual}checked{/if}> {_p var='hulahoot_yes'}
                </label>
                <span class="help-block">{_p var='hulahoot_registration_allowed_help'}</span>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_field_requires_category'}</label>
            <div class="col-sm-9">
                <label class="checkbox-inline">
                    <input type="checkbox" name="requires_category" value="1" {if $values.requires_category}checked{/if}> {_p var='hulahoot_yes'}
                </label>
            </div>
        </div>

        <details class="hulahoot-advanced">
            <summary>{_p var='hulahoot_advanced_settings'}</summary>
            <div class="hulahoot-advanced-body">
                <div class="form-group">
                    <label for="hulahoot_type_name_url" class="col-sm-3 control-label">{_p var='hulahoot_field_slug'}</label>
                    <div class="col-sm-9">
                        <input type="text" name="name_url" id="hulahoot_type_name_url" class="form-control" maxlength="100" value="{$values.name_url|clean}" placeholder="{_p var='hulahoot_slug_placeholder'}">
                        <span class="help-block">{_p var='hulahoot_slug_help'}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="hulahoot_type_description" class="col-sm-3 control-label">{_p var='hulahoot_field_description'}</label>
                    <div class="col-sm-9">
                        <textarea name="description" id="hulahoot_type_description" class="form-control" maxlength="255" rows="3">{$values.description|clean}</textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label for="hulahoot_type_icon" class="col-sm-3 control-label">{_p var='hulahoot_field_icon'}</label>
                    <div class="col-sm-9">
                        <input type="text" name="icon" id="hulahoot_type_icon" class="form-control" maxlength="100" value="{$values.icon|clean}">
                    </div>
                </div>

                <div class="form-group">
                    <label for="hulahoot_type_ordering" class="col-sm-3 control-label">{_p var='hulahoot_field_sort_order'}</label>
                    <div class="col-sm-9">
                        <input type="number" name="ordering" id="hulahoot_type_ordering" class="form-control" value="{$values.ordering}">
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label">{_p var='hulahoot_field_user_creatable'}</label>
                    <div class="col-sm-9">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="is_user_creatable" value="1" {if $values.is_user_creatable}checked{/if}> {_p var='hulahoot_yes'}
                        </label>
                        <span class="help-block">{_p var='hulahoot_user_creatable_help'}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label">{_p var='hulahoot_field_is_default'}</label>
                    <div class="col-sm-9">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="is_default" value="1" {if $values.is_default}checked{/if}> {_p var='hulahoot_yes'}
                        </label>
                        <span class="help-block">{_p var='hulahoot_is_default_help'}</span>
                    </div>
                </div>
            </div>
        </details>

        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
                <button type="submit" class="btn btn-primary">{_p var='hulahoot_save_changes'}</button>
                <a href="/admincp/hulahoot/profiletype" class="btn btn-link">{_p var='hulahoot_cancel'}</a>
            </div>
        </div>
    </form>
</div>
