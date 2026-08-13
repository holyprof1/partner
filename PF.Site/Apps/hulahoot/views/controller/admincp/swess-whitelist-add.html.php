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
    .hulahoot-admin-form .form-group { margin-bottom: 16px; }
    .hulahoot-admin-form label.control-label { font-weight: 600; }
    .hulahoot-admin-form .help-block { margin-top: 4px; font-size: 12.5px; }
    .hulahoot-swess-section { margin: 30px 0 0; padding-top: 22px; border-top: 1px solid #e5e5e5; }
    .hulahoot-swess-section h2 { font-size: 16px; font-weight: 600; margin: 0 0 14px; }
    .hulahoot-swess-identity-card { border: 1px solid #e2e2e2; border-radius: 4px; padding: 12px 14px; margin-bottom: 10px; background: #fff; }
    .hulahoot-swess-identity-card .hulahoot-swess-identity-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .hulahoot-swess-tag-chip { display: inline-block; background: #f0f0f0; border-radius: 999px; padding: 2px 10px; font-size: 12px; margin: 6px 6px 0 0; }
    .hulahoot-swess-tag-chip.is-default { background: #000; color: #fff; }
    .hulahoot-swess-tag-chip form { display: inline; margin-left: 6px; }
    .hulahoot-swess-tag-chip button { border: none; background: none; color: inherit; cursor: pointer; padding: 0; font-size: 11px; }
    .hulahoot-swess-inline-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; }
    .hulahoot-swess-inline-form select, .hulahoot-swess-inline-form input[type=text] { padding: 4px 8px; }
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
        <h1><span class="hulahoot-swess-mark">SW</span>{if $owner}{_p var='hulahoot_edit_swess_whitelist'}{else}{_p var='hulahoot_add_swess_whitelist'}{/if}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/swess/whitelist" class="btn btn-default">{_p var='hulahoot_back_to_swess_whitelist'}</a>
        </div>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <form method="post" action="/admincp/hulahoot/swess/whitelist/add{if $whitelist_id}?id={$whitelist_id}{/if}" class="form-horizontal hulahoot-admin-form">
        <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
        <input type="hidden" name="do" value="save_whitelist">

        {if $owner}
            <div class="form-group">
                <label class="col-sm-3 control-label">{_p var='hulahoot_field_user'}</label>
                <div class="col-sm-9">
                    <p class="form-control-static">{$owner.user_name} <span class="text-muted">({$owner.full_name})</span></p>
                </div>
            </div>
        {else}
            <div class="form-group">
                <label for="hulahoot_swess_user_lookup" class="col-sm-3 control-label">{_p var='hulahoot_field_user'}</label>
                <div class="col-sm-9">
                    <input type="text" name="user_lookup" id="hulahoot_swess_user_lookup" class="form-control" placeholder="{_p var='hulahoot_swess_user_lookup_placeholder'}" required autofocus>
                    <span class="help-block">{_p var='hulahoot_swess_user_lookup_help'}</span>
                </div>
            </div>
        {/if}

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_swess_field_enabled'}</label>
            <div class="col-sm-9">
                <label class="checkbox-inline">
                    <input type="checkbox" name="is_enabled" value="1" {if $whitelist.is_enabled}checked{/if}> {_p var='hulahoot_swess_enabled_help'}
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_swess_field_post_as_self'}</label>
            <div class="col-sm-9">
                <label class="checkbox-inline">
                    <input type="checkbox" name="post_as_self" value="1" {if $whitelist.post_as_self}checked{/if}> {_p var='hulahoot_yes'}
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_swess_field_post_as_business'}</label>
            <div class="col-sm-9">
                <label class="checkbox-inline">
                    <input type="checkbox" name="post_as_business" value="1" {if $whitelist.post_as_business}checked{/if}> {_p var='hulahoot_yes'}
                </label>
                <span class="help-block">{_p var='hulahoot_swess_post_as_business_help'}</span>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_swess_field_requires_review'}</label>
            <div class="col-sm-9">
                <label class="checkbox-inline">
                    <input type="checkbox" name="requires_review" value="1" {if $whitelist.requires_review}checked{/if}> {_p var='hulahoot_swess_requires_review_help'}
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_swess_field_allowed_target_levels'}</label>
            <div class="col-sm-9">
                {foreach from=$target_levels item=aLevel}
                    <label class="checkbox-inline">
                        <input type="checkbox" name="allowed_target_levels[]" value="{$aLevel.value}" {if in_array($aLevel.value, $allowed_levels)}checked{/if}> {$aLevel.label}
                    </label>
                {/foreach}
                <span class="help-block">{_p var='hulahoot_swess_allowed_target_levels_help'}</span>
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
                <button type="submit" class="btn btn-primary">{_p var='hulahoot_save_changes'}</button>
            </div>
        </div>
    </form>

    {if $whitelist_id}
        <div class="hulahoot-swess-section">
            <h2>{_p var='hulahoot_swess_approved_identities'}</h2>
            <p class="help-block">{_p var='hulahoot_swess_approved_identities_help'}</p>

            {foreach from=$identities item=aIdentity}
                <div class="hulahoot-swess-identity-card">
                    <div class="hulahoot-swess-identity-head">
                        <div>
                            <strong>{if $aIdentity.identity_type == 'self'}{_p var='hulahoot_swess_identity_self'}{else}{_p var='hulahoot_swess_identity_page'} #{$aIdentity.identity_id}{/if}</strong>
                            {if !$aIdentity.is_active}<span class="label label-default">{_p var='hulahoot_inactive'}</span>{/if}
                        </div>
                        {if $aIdentity.is_active}
                            <form method="post" action="/admincp/hulahoot/swess/whitelist/add?id={$whitelist_id}">
                                <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
                                <input type="hidden" name="do" value="revoke_identity">
                                <input type="hidden" name="approved_identity_id" value="{$aIdentity.approved_identity_id}">
                                <button type="submit" class="btn btn-danger btn-sm">{_p var='hulahoot_swess_revoke'}</button>
                            </form>
                        {/if}
                    </div>

                    <div>
                        {foreach from=$aIdentity.tags item=aIdentityTag}
                            <span class="hulahoot-swess-tag-chip{if $aIdentityTag.is_default} is-default{/if}">
                                {$aIdentityTag.name}
                                <form method="post" action="/admincp/hulahoot/swess/whitelist/add?id={$whitelist_id}">
                                    <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
                                    <input type="hidden" name="do" value="unassign_tag">
                                    <input type="hidden" name="approved_identity_id" value="{$aIdentity.approved_identity_id}">
                                    <input type="hidden" name="tag_id" value="{$aIdentityTag.tag_id}">
                                    <button type="submit" title="{_p var='hulahoot_swess_remove_tag'}">&times;</button>
                                </form>
                            </span>
                        {/foreach}
                    </div>

                    <form method="post" action="/admincp/hulahoot/swess/whitelist/add?id={$whitelist_id}" class="hulahoot-swess-inline-form">
                        <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
                        <input type="hidden" name="do" value="assign_tag">
                        <input type="hidden" name="approved_identity_id" value="{$aIdentity.approved_identity_id}">
                        <select name="tag_id" class="form-control input-sm" required>
                            <option value="">{_p var='hulahoot_swess_choose_tag'}</option>
                            {foreach from=$tags item=aTag}
                                <option value="{$aTag.tag_id}">{$aTag.name}</option>
                            {/foreach}
                        </select>
                        <label class="checkbox-inline"><input type="checkbox" name="is_default" value="1"> {_p var='hulahoot_swess_make_default'}</label>
                        <button type="submit" class="btn btn-default btn-sm">{_p var='hulahoot_swess_assign_tag'}</button>
                    </form>
                </div>
            {foreachelse}
                <p class="text-muted">{_p var='hulahoot_swess_no_identities_yet'}</p>
            {/foreach}

            <form method="post" action="/admincp/hulahoot/swess/whitelist/add?id={$whitelist_id}" class="hulahoot-swess-inline-form">
                <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
                <input type="hidden" name="do" value="approve_identity">
                <select name="identity_type" class="form-control input-sm" id="js_hulahoot_swess_identity_type">
                    <option value="self">{_p var='hulahoot_swess_identity_self'}</option>
                    <option value="page">{_p var='hulahoot_swess_identity_page'}</option>
                </select>
                <input type="text" name="identity_id" class="form-control input-sm" placeholder="{_p var='hulahoot_swess_page_id_placeholder'}">
                <button type="submit" class="btn btn-primary btn-sm">{_p var='hulahoot_swess_approve_identity'}</button>
            </form>
            <p class="help-block">{_p var='hulahoot_swess_page_id_help'}</p>
        </div>
    {/if}
</div>
