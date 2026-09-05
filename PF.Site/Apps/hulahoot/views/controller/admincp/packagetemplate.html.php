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
    .hulahoot-admin-table { background: #fff; }
    .hulahoot-admin-table th {
        font-size: 12px; text-transform: uppercase; letter-spacing: .03em;
        color: #767676; font-weight: 600; border-bottom-width: 1px !important;
    }
    .hulahoot-admin-table td { vertical-align: middle !important; }
    .hulahoot-admin-actions { white-space: nowrap; text-align: right; }
    .hulahoot-admin-actions .btn { margin-left: 4px; }
    .hulahoot-admin-empty { text-align: center; color: #888; padding: 28px 10px !important; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1>{_p var='hulahoot_admin_package_templates'}</h1>
        <div class="hulahoot-header-actions">
            <a href="/admincp/hulahoot/packagetemplate/add" class="btn btn-primary">
                <i class="fa fa-plus"></i> {_p var='hulahoot_add_template'}
            </a>
        </div>
    </div>

    <p class="help-block">{_p var='hulahoot_package_templates_intro'}</p>

    <table class="table table-bordered table-striped hulahoot-admin-table">
        <thead>
            <tr>
                <th>{_p var='hulahoot_field_name'}</th>
                <th>{_p var='hulahoot_field_native_price'}</th>
                <th>{_p var='hulahoot_field_active'}</th>
                <th>{_p var='hulahoot_field_actions'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$templates item=aTemplate}
                <tr>
                    <td>{$aTemplate.name|clean}</td>
                    <td>
                        {if $aTemplate.default_cost}
                            {$aTemplate.default_cost|clean} USD
                        {else}
                            {_p var='hulahoot_free'}
                        {/if}
                    </td>
                    <td>{if $aTemplate.is_active}<span class="label label-success">{_p var='hulahoot_active'}</span>{else}<span class="label label-default">{_p var='hulahoot_inactive'}</span>{/if}</td>
                    <td class="hulahoot-admin-actions">
                        <a href="/admincp/hulahoot/packagetemplate/add?id={$aTemplate.template_id}" class="btn btn-default btn-sm">{_p var='hulahoot_edit'}</a>
                        <a href="/admincp/hulahoot/packagetemplate/delete?id={$aTemplate.template_id}" class="btn btn-danger btn-sm">{_p var='hulahoot_delete'}</a>
                    </td>
                </tr>
            {foreachelse}
                <tr>
                    <td colspan="4" class="hulahoot-admin-empty">{_p var='hulahoot_no_templates_found'}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
