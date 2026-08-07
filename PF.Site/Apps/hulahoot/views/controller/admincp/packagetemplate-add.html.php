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
    .hulahoot-form-section {
        margin: 26px 0 18px; padding-top: 18px; border-top: 1px solid #e5e5e5;
    }
    .hulahoot-form-section:first-of-type { margin-top: 6px; padding-top: 0; border-top: none; }
    .hulahoot-form-section-title {
        font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
        color: #767676; margin: 0 0 16px;
    }
    .hulahoot-color-swatch {
        display: inline-block; width: 20px; height: 20px; border-radius: 4px;
        vertical-align: middle; margin-left: 8px; border: 1px solid #ccc;
    }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{if $template_id}{_p var='hulahoot_edit_template'}{else}{_p var='hulahoot_add_template'}{/if}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/packagetemplate" class="btn btn-default">{_p var='hulahoot_back_to_templates'}</a>
        </div>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <form method="post" action="/admincp/hulahoot/packagetemplate/add?id={$template_id}" class="form-horizontal hulahoot-admin-form">
        <input type="hidden" name="hulahoot_token" value="{$csrf_token}">

        <div class="hulahoot-form-section">
            <p class="hulahoot-form-section-title">{_p var='hulahoot_section_basics'}</p>

            <div class="form-group">
                <label for="hulahoot_name" class="col-sm-3 control-label">{_p var='hulahoot_field_name'}</label>
                <div class="col-sm-9">
                    <input type="text" name="name" id="hulahoot_name" class="form-control" maxlength="100" value="{$template.name|clean}" placeholder="{_p var='hulahoot_template_name_placeholder'}" required autofocus>
                    <span class="help-block">{_p var='hulahoot_template_name_help'}</span>
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_default_cost" class="col-sm-3 control-label">{_p var='hulahoot_field_default_cost'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="default_cost" id="hulahoot_default_cost" class="form-control" value="{$template.default_cost}" placeholder="0">
                    <span class="help-block">{_p var='hulahoot_default_cost_help'}</span>
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_recurring_period" class="col-sm-3 control-label">{_p var='hulahoot_field_recurring_period'}</label>
                <div class="col-sm-9">
                    <select name="recurring_period" id="hulahoot_recurring_period" class="form-control">
                        <option value="0" {if $template.recurring_period == 0}selected{/if}>{_p var='hulahoot_recurring_none'}</option>
                        <option value="1" {if $template.recurring_period == 1}selected{/if}>{_p var='hulahoot_recurring_monthly'}</option>
                        <option value="2" {if $template.recurring_period == 2}selected{/if}>{_p var='hulahoot_recurring_quarterly'}</option>
                        <option value="3" {if $template.recurring_period == 3}selected{/if}>{_p var='hulahoot_recurring_biannual'}</option>
                        <option value="4" {if $template.recurring_period == 4}selected{/if}>{_p var='hulahoot_recurring_yearly'}</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_description" class="col-sm-3 control-label">{_p var='hulahoot_field_description'}</label>
                <div class="col-sm-9">
                    <textarea name="description" id="hulahoot_description" class="form-control" rows="3">{$template.description|clean}</textarea>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-9 col-sm-offset-3">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="is_active" value="1" {if $template.is_active}checked{/if}> {_p var='hulahoot_template_active_here'}
                    </label>
                    <span class="help-block">{_p var='hulahoot_template_active_help'}</span>
                </div>
            </div>
        </div>

        <div class="hulahoot-form-section">
            <p class="hulahoot-form-section-title">{_p var='hulahoot_section_presentation'}</p>

            <div class="form-group">
                <label for="hulahoot_subtitle" class="col-sm-3 control-label">{_p var='hulahoot_field_subtitle'}</label>
                <div class="col-sm-9">
                    <input type="text" name="subtitle" id="hulahoot_subtitle" class="form-control" maxlength="255" value="{$template.subtitle|clean}" placeholder="{_p var='hulahoot_subtitle_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_badge_text" class="col-sm-3 control-label">{_p var='hulahoot_field_badge_text'}</label>
                <div class="col-sm-9">
                    <input type="text" name="badge_text" id="hulahoot_badge_text" class="form-control" maxlength="50" value="{$template.badge_text|clean}" placeholder="{_p var='hulahoot_badge_text_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_button_text" class="col-sm-3 control-label">{_p var='hulahoot_field_button_text'}</label>
                <div class="col-sm-9">
                    <input type="text" name="button_text" id="hulahoot_button_text" class="form-control" maxlength="50" value="{$template.button_text|clean}" placeholder="{_p var='hulahoot_button_text_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_accent_color" class="col-sm-3 control-label">{_p var='hulahoot_field_accent_color'}</label>
                <div class="col-sm-9">
                    <input type="text" name="accent_color" id="hulahoot_accent_color" class="form-control" style="max-width:160px;display:inline-block;" maxlength="20" value="{$template.accent_color|clean}" placeholder="#2C7BE5">
                    {if $template.accent_color}<span class="hulahoot-color-swatch" style="background-color:{$template.accent_color|clean};"></span>{/if}
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_ordering" class="col-sm-3 control-label">{_p var='hulahoot_field_sort_order'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="ordering" id="hulahoot_ordering" class="form-control" value="{$template.ordering}">
                </div>
            </div>
        </div>

        <div class="hulahoot-form-section">
            <p class="hulahoot-form-section-title">{_p var='hulahoot_section_limits'}</p>

            <div class="form-group">
                <label for="hulahoot_purchase_limit" class="col-sm-3 control-label">{_p var='hulahoot_field_purchase_limit'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="purchase_limit" id="hulahoot_purchase_limit" class="form-control" value="{$template.purchase_limit}" placeholder="{_p var='hulahoot_unlimited_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_campaign_limit" class="col-sm-3 control-label">{_p var='hulahoot_field_campaign_limit'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="campaign_limit" id="hulahoot_campaign_limit" class="form-control" value="{$template.campaign_limit}" placeholder="{_p var='hulahoot_unlimited_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_posting_limit_per_day" class="col-sm-3 control-label">{_p var='hulahoot_field_posting_limit_per_day'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="posting_limit_per_day" id="hulahoot_posting_limit_per_day" class="form-control" value="{$template.posting_limit_per_day}" placeholder="{_p var='hulahoot_unlimited_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_posting_limit_per_month" class="col-sm-3 control-label">{_p var='hulahoot_field_posting_limit_per_month'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="posting_limit_per_month" id="hulahoot_posting_limit_per_month" class="form-control" value="{$template.posting_limit_per_month}" placeholder="{_p var='hulahoot_unlimited_placeholder'}">
                </div>
            </div>

            <div class="form-group">
                <label for="hulahoot_monthly_credits" class="col-sm-3 control-label">{_p var='hulahoot_field_monthly_credits'}</label>
                <div class="col-sm-9">
                    <input type="number" min="0" name="monthly_credits" id="hulahoot_monthly_credits" class="form-control" value="{$template.monthly_credits}">
                </div>
            </div>
        </div>

        <div class="hulahoot-form-section">
            <p class="hulahoot-form-section-title">{_p var='hulahoot_section_features'}</p>

            <div class="form-group">
                <label for="hulahoot_features_text" class="col-sm-3 control-label">{_p var='hulahoot_field_features'}</label>
                <div class="col-sm-9">
                    <textarea name="features_text" id="hulahoot_features_text" class="form-control" rows="8" placeholder="{_p var='hulahoot_features_placeholder'}">{$template.features_text|clean}</textarea>
                    <span class="help-block">{_p var='hulahoot_features_help'}</span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
                <button type="submit" class="btn btn-primary">{_p var='hulahoot_save_changes'}</button>
                <a href="/admincp/hulahoot/packagetemplate" class="btn btn-link">{_p var='hulahoot_cancel'}</a>
            </div>
        </div>
    </form>
</div>
