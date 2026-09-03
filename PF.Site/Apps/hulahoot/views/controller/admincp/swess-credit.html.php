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
    .hulahoot-admin-form .form-group { margin-bottom: 16px; }
    .hulahoot-admin-form label.control-label { font-weight: 600; }
    .hulahoot-admin-form .help-block { margin-top: 4px; font-size: 12.5px; }
    .hulahoot-swess-mark {
        display: inline-flex; align-items: center; justify-content: center;
        width: 26px; height: 26px; border-radius: 7px; background: #000; color: #fff;
        font-size: 11px; font-weight: 800; letter-spacing: -.02em; margin-right: 8px;
        vertical-align: middle;
    }
    .hulahoot-swess-user-search { position: relative; }
    .hulahoot-swess-user-search-results {
        display: none; position: absolute; z-index: 20; top: 100%; left: 0; right: 0;
        background: #fff; border: 1px solid #d5d5d5; border-top: none; border-radius: 0 0 4px 4px;
        max-height: 240px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,.08);
    }
    .hulahoot-swess-user-search-results.is-open { display: block; }
    .hulahoot-swess-user-search-result { padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
    .hulahoot-swess-user-search-result:last-child { border-bottom: none; }
    .hulahoot-swess-user-search-result:hover { background: #f3f3f3; }
    .hulahoot-swess-user-search-result .hulahoot-swess-user-search-email { color: #888; font-size: 12px; margin-left: 4px; }
    .hulahoot-swess-user-search-empty { padding: 8px 12px; color: #888; font-size: 13px; }
    .hulahoot-credit-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin: 0 0 24px; }
    @media (max-width: 720px) { .hulahoot-credit-stats { grid-template-columns: repeat(2, 1fr); } }
    .hulahoot-credit-stat { border: 1px solid #e2e2e2; border-radius: 8px; padding: 14px 16px; background: #fff; }
    .hulahoot-credit-stat .num { font-size: 22px; font-weight: 800; }
    .hulahoot-credit-stat .label { font-size: 11px; color: #9a9a9a; text-transform: uppercase; letter-spacing: .03em; margin-top: 2px; }
    .hulahoot-credit-stat.available .num { color: #000; }
    table.hulahoot-ledger-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
    table.hulahoot-ledger-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #9a9a9a; padding: 8px; border-bottom: 1px solid #e5e5e5; }
    table.hulahoot-ledger-table td { padding: 8px; border-bottom: 1px solid #f0f0f0; }
{/literal}
</style>
<div class="hulahoot-admin">
    <div class="page-header hulahoot-page-header">
        <h1><span class="hulahoot-swess-mark">SW</span>{_p var='hulahoot_admin_swess_credits'}</h1>
    </div>

    {if $error}
        <div class="alert alert-danger">{$error|clean}</div>
    {/if}

    <form method="get" action="/admincp/hulahoot/swess/credits" class="hulahoot-admin-form">
        <div class="form-group">
            <label for="hulahoot_swess_credit_user_lookup" class="control-label">{_p var='hulahoot_field_user'}</label>
            <div class="hulahoot-swess-user-search">
                <input type="text" id="hulahoot_swess_credit_user_lookup" class="form-control" placeholder="{_p var='hulahoot_swess_user_lookup_placeholder'}" autocomplete="off">
                <div id="hulahoot_swess_credit_user_lookup_results" class="hulahoot-swess-user-search-results"></div>
            </div>
            <span class="help-block">{_p var='hulahoot_swess_credit_lookup_help'}</span>
        </div>
    </form>

    {if $owner}
        <h3>{$owner.user_name} <span class="text-muted">({$owner.full_name})</span></h3>

        {if $balance.exempt}
            <p class="text-muted">{_p var='hulahoot_swess_credit_exempt'}</p>
        {else}
            <div class="hulahoot-credit-stats">
                <div class="hulahoot-credit-stat"><div class="num">{$balance.allocation}</div><div class="label">{_p var='hulahoot_swess_credit_allocation'}</div></div>
                <div class="hulahoot-credit-stat"><div class="num">{$balance.bonus}</div><div class="label">{_p var='hulahoot_swess_credit_bonus'}</div></div>
                <div class="hulahoot-credit-stat"><div class="num">{$balance.reserved}</div><div class="label">{_p var='hulahoot_swess_credit_reserved'}</div></div>
                <div class="hulahoot-credit-stat"><div class="num">{$balance.used}</div><div class="label">{_p var='hulahoot_swess_credit_used'}</div></div>
                <div class="hulahoot-credit-stat available"><div class="num">{$balance.available}</div><div class="label">{_p var='hulahoot_swess_credit_available'}</div></div>
            </div>
        {/if}

        <h4>{_p var='hulahoot_swess_credit_adjust'}</h4>
        <form method="post" action="/admincp/hulahoot/swess/credits?user_id={$owner.user_id}" class="hulahoot-admin-form">
            <input type="hidden" name="hulahoot_token" value="{$csrf_token}">
            <input type="hidden" name="do" value="adjust_bonus">
            <div class="form-group">
                <label class="control-label">{_p var='hulahoot_swess_credit_direction'}</label>
                <label class="radio-inline"><input type="radio" name="direction" value="grant" checked> {_p var='hulahoot_swess_credit_grant'}</label>
                <label class="radio-inline"><input type="radio" name="direction" value="revoke"> {_p var='hulahoot_swess_credit_revoke'}</label>
            </div>
            <div class="form-group">
                <label for="hulahoot_swess_credit_amount" class="control-label">{_p var='hulahoot_swess_credit_amount'}</label>
                <input type="number" min="1" step="1" name="amount" id="hulahoot_swess_credit_amount" class="form-control" style="max-width:160px;" required>
            </div>
            <div class="form-group">
                <label for="hulahoot_swess_credit_note" class="control-label">{_p var='hulahoot_swess_credit_note'}</label>
                <input type="text" name="note" id="hulahoot_swess_credit_note" class="form-control" placeholder="{_p var='hulahoot_swess_credit_note_placeholder'}" required>
                <span class="help-block">{_p var='hulahoot_swess_credit_note_help'}</span>
            </div>
            <button type="submit" class="btn btn-primary">{_p var='hulahoot_swess_credit_apply'}</button>
        </form>

        <h4>{_p var='hulahoot_swess_credit_history'}</h4>
        {if $ledger}
            <table class="hulahoot-ledger-table">
                <thead><tr><th>{_p var='hulahoot_field_date'}</th><th>{_p var='hulahoot_swess_credit_type'}</th><th>{_p var='hulahoot_swess_credit_amount'}</th><th>{_p var='hulahoot_swess_field_context'}</th></tr></thead>
                <tbody>
                    {foreach from=$ledger item=aTx}
                        <tr>
                            <td>{$aTx.created_display}</td>
                            <td>{_p var="hulahoot_swess_credit_type_`$aTx.type`"}</td>
                            <td>{$aTx.amount}</td>
                            <td>{$aTx.note|default:'&mdash;'|clean} {if $aTx.swess_post_id}(#{$aTx.swess_post_id}){/if}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <p class="text-muted">{_p var='hulahoot_swess_credit_no_history'}</p>
        {/if}
    {/if}
</div>
<script>
(function () {
    var input = document.getElementById('hulahoot_swess_credit_user_lookup');
    var results = document.getElementById('hulahoot_swess_credit_user_lookup_results');
    if (!input || !results) {
        return;
    }

    var debounceTimer = null;

    function closeResults() {
        results.classList.remove('is-open');
        results.innerHTML = '';
    }

    function renderResults(users) {
        results.innerHTML = '';

        if (!users.length) {
            var empty = document.createElement('div');
            empty.className = 'hulahoot-swess-user-search-empty';
            empty.textContent = '{_p var="hulahoot_swess_search_no_results" phpfox_squote=true}';
            results.appendChild(empty);
            results.classList.add('is-open');
            return;
        }

        users.forEach(function (user) {
            var row = document.createElement('div');
            row.className = 'hulahoot-swess-user-search-result';

            var nameSpan = document.createElement('span');
            nameSpan.textContent = user.user_name + (user.full_name ? ' (' + user.full_name + ')' : '');
            row.appendChild(nameSpan);

            var emailSpan = document.createElement('span');
            emailSpan.className = 'hulahoot-swess-user-search-email';
            emailSpan.textContent = user.email || '';
            row.appendChild(emailSpan);

            row.addEventListener('click', function () {
                window.location.href = '/admincp/hulahoot/swess/credits?user_id=' + user.user_id;
            });

            results.appendChild(row);
        });

        results.classList.add('is-open');
    }

    input.addEventListener('input', function () {
        var query = input.value.trim();
        window.clearTimeout(debounceTimer);

        if (query.length < 2) {
            closeResults();
            return;
        }

        debounceTimer = window.setTimeout(function () {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/admincp/hulahoot/swess/whitelist/search-users?q=' + encodeURIComponent(query), true);
            xhr.onload = function () {
                if (xhr.status !== 200) {
                    return;
                }
                try {
                    renderResults(JSON.parse(xhr.responseText));
                } catch (e) {
                    closeResults();
                }
            };
            xhr.send();
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (e.target !== input && !results.contains(e.target)) {
            closeResults();
        }
    });
})();
</script>
