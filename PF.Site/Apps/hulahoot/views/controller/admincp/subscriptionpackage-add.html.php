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
    .hulahoot-industry-checkbox { display: inline-block; margin: 0 16px 8px 0; }
    .hulahoot-form-section {
        margin: 26px 0 18px; padding-top: 18px; border-top: 1px solid #e5e5e5;
    }
    .hulahoot-form-section:first-of-type { margin-top: 6px; padding-top: 0; border-top: none; }
    .hulahoot-form-section-title {
        font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
        color: #767676; margin: 0 0 16px;
    }
    .hulahoot-current-image {
        display: block; max-width: 240px; max-height: 120px; margin-bottom: 8px;
        border-radius: 4px; border: 1px solid #e2e2e2;
    }
    .hulahoot-color-swatch {
        display: inline-block; width: 20px; height: 20px; border-radius: 4px;
        vertical-align: middle; margin-left: 8px; border: 1px solid #ccc;
    }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{_p var='hulahoot_edit_subscription_package_rules'}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/subscriptionpackage" class="btn btn-default">{_p var='hulahoot_back_to_subscription_packages'}</a>
        </div>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <div class="form-horizontal hulahoot-admin-form">
        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_field_native_package'}</label>
            <div class="col-sm-9">
                <p class="form-control-static">
                    {_p var=$package.title}
                    {if $package.default_cost}
                        &mdash; {$package.default_cost} {$package.default_currency_id|clean}
                    {else}
                        &mdash; {_p var='hulahoot_free'}
                    {/if}
                    {if $package.is_active}
                        <span class="label label-success">{_p var='hulahoot_active'}</span>
                    {else}
                        <span class="label label-default">{_p var='hulahoot_inactive'}</span>
                    {/if}
                </p>
                <span class="help-block">{_p var='hulahoot_native_package_help'}</span>
            </div>
        </div>
    </div>

    <form method="post" action="/admincp/hulahoot/subscriptionpackage/edit?id={$package_id}" enctype="multipart/form-data" class="form-horizontal hulahoot-admin-form">
        <input type="hidden" name="hulahoot_token" value="{$csrf_token}">

        <div class="form-group">
            <label class="col-sm-3 control-label">{_p var='hulahoot_field_hulahoot_rules'}</label>
            <div class="col-sm-9">
                <label class="checkbox-inline">
                    <input type="checkbox" name="is_active" value="1" {if $rules.is_active}checked{/if}> {_p var='hulahoot_rules_active_here'}
                </label>
                <span class="help-block">{_p var='hulahoot_rules_active_help'}</span>
            </div>
        </div>

        <div class="hulahoot-form-section">
            <p class="hulahoot-form-section-title">{_p var='hulahoot_section_presentation'}</p>

            <div class="form-group">
                <label for="hulahoot_subtitle" class="col-sm-3 control-label">{_p var='hulahoot_field_subtitle'}</label>
                <div class="col-sm-9">
                    <input type="text" name="subtitle" id="hulahoot_subtitle" class="form-control" maxlength="255" value="{$rules.subtitle|clean}" placeholder="{_p var='hulahoot_subtitle_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_description" class="col-sm-3 control-label">{_p var='hulahoot_field_description'}</label>
                <div class="col-sm-9">
                    <textarea name="description" id="hulahoot_description" class="form-control" rows="3">{$rules.description|clean}</textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_badge_text" class="col-sm-3 control-label">{_p var='hulahoot_field_badge_text'}</label>
                <div class="col-sm-9">
                    <input type="text" name="badge_text" id="hulahoot_badge_text" class="form-control" maxlength="50" value="{$rules.badge_text|clean}" placeholder="{_p var='hulahoot_badge_text_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_button_text" class="col-sm-3 control-label">{_p var='hulahoot_field_button_text'}</label>
                <div class="col-sm-9">
                    <input type="text" name="button_text" id="hulahoot_button_text" class="form-control" maxlength="50" value="{$rules.button_text|clean}" placeholder="{_p var='hulahoot_button_text_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_accent_color" class="col-sm-3 control-label">{_p var='hulahoot_field_accent_color'}</label>
                <div class="col-sm-9">
                    <input type="text" name="accent_color" id="hulahoot_accent_color" class="form-control" style="max-width:160px;display:inline-block;" maxlength="20" value="{$rules.accent_color|clean}" placeholder="#2C7BE5">
                    {if $rules.accent_color}<span class="hulahoot-color-swatch" style="background-color:{$rules.accent_color|clean};"></span>{/if}
                    <span class="help-block">{_p var='hulahoot_accent_color_help'}</span>
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_package_image" class="col-sm-3 control-label">{_p var='hulahoot_field_package_image'}</label>
                <div class="col-sm-9">
                    {if $image_url}
                        <img class="hulahoot-current-image" src="{$image_url}" alt="">
                    {/if}
                    <input type="file" name="image" id="hulahoot_package_image" accept="image/jpeg,image/png,image/gif">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_ordering" class="col-sm-3 control-label">{_p var='hulahoot_field_sort_order'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="ordering" id="hulahoot_ordering" class="form-control" value="{$rules.ordering}">
                    <span class="help-block">{_p var='hulahoot_package_ordering_help'}</span>
                </div>
            </div>
        </div>

        <div class="hulahoot-form-section">
            <p class="hulahoot-form-section-title">{_p var='hulahoot_section_limits'}</p>

            <div class="form-group">
                <label for="hulahoot_purchase_limit" class="col-sm-3 control-label">{_p var='hulahoot_field_purchase_limit'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="purchase_limit" id="hulahoot_purchase_limit" class="form-control" value="{$rules.purchase_limit}" placeholder="{_p var='hulahoot_unlimited_placeholder'}">
                    <span class="help-block">{_p var='hulahoot_purchase_limit_help'}</span>
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_campaign_limit" class="col-sm-3 control-label">{_p var='hulahoot_field_campaign_limit'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="campaign_limit" id="hulahoot_campaign_limit" class="form-control" value="{$rules.campaign_limit}" placeholder="{_p var='hulahoot_unlimited_placeholder'}">
                    <span class="help-block">{_p var='hulahoot_campaign_limit_help'}</span>
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_posting_limit_per_day" class="col-sm-3 control-label">{_p var='hulahoot_field_posting_limit_per_day'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="posting_limit_per_day" id="hulahoot_posting_limit_per_day" class="form-control" value="{$rules.posting_limit_per_day}" placeholder="{_p var='hulahoot_unlimited_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_posting_limit_per_month" class="col-sm-3 control-label">{_p var='hulahoot_field_posting_limit_per_month'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="posting_limit_per_month" id="hulahoot_posting_limit_per_month" class="form-control" value="{$rules.posting_limit_per_month}" placeholder="{_p var='hulahoot_unlimited_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_monthly_credits" class="col-sm-3 control-label">{_p var='hulahoot_field_monthly_credits'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="monthly_credits" id="hulahoot_monthly_credits" class="form-control" value="{$rules.monthly_credits}">
                    <span class="help-block">{_p var='hulahoot_monthly_credits_help'}</span>
                </div>
            </div>
        </div>

        <div class="hulahoot-form-section">
            <p class="hulahoot-form-section-title">{_p var='hulahoot_section_features'}</p>

            <div class="form-group">
                <label for="hulahoot_features" class="col-sm-3 control-label">{_p var='hulahoot_field_features'}</label>
                <div class="col-sm-9">
                    <textarea name="features" id="hulahoot_features" class="form-control" rows="8" placeholder="{_p var='hulahoot_features_placeholder'}">{$features_text|clean}</textarea>
                    <span class="help-block">{_p var='hulahoot_features_help'}</span>
                </div>
            </div>
        </div>

        <div class="hulahoot-form-section">
            <p class="hulahoot-form-section-title">{_p var='hulahoot_field_industries'}</p>

            <div class="form-group">
                <div class="col-sm-9 col-sm-offset-3">
                    {foreach from=$industries item=aIndustry}
                        {assign var="hulahootIndustryId" value=$aIndustry.industry_id}
                        <label class="checkbox-inline hulahoot-industry-checkbox">
                            <input type="checkbox" name="industry_ids[]" value="{$hulahootIndustryId}" {if $selected_industry_map[$hulahootIndustryId]}checked{/if}> {_p var=$aIndustry.name}
                        </label>
                    {foreachelse}
                        <p class="form-control-static">{_p var='hulahoot_no_industries_found'}</p>
                    {/foreach}
                    <span class="help-block">{_p var='hulahoot_industries_help'}</span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
                <button type="submit" class="btn btn-primary">{_p var='hulahoot_save_changes'}</button>
                <a href="/admincp/hulahoot/subscriptionpackage" class="btn btn-link">{_p var='hulahoot_cancel'}</a>
            </div>
        </div>
    </form>
</div>
