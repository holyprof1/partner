<?php

defined('PHPFOX') or exit('NO DICE!');

?>
<style>
{literal}
/* Scoped to .hulahoot-industry-section only. No selector in this block
   touches #main, #content-holder, .layout-middle, or any other shared
   phpFox container - it defines its own width/spacing and simply sits as
   a normal sibling above the native Feed block in the same location, so
   changes to the Feed's own width/margins/settings cannot reach here,
   and changes here cannot reach the Feed. */
.hulahoot-industry-section {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    padding: 24px 20px 8px;
    box-sizing: border-box;
}
.hulahoot-industry-section * {
    box-sizing: border-box;
}
.hulahoot-industry-section .hulahoot-marketplace-hero {
    text-align: center;
    padding: 6px 15px 20px;
}
.hulahoot-industry-section .hulahoot-marketplace-hero h1 {
    font-size: 34px;
    font-weight: 800;
    margin: 0 0 10px;
    color: #000;
}
.hulahoot-industry-section .hulahoot-marketplace-hero p {
    color: #797979;
    font-size: 16px;
    margin: 0 auto 24px;
    max-width: 560px;
}
.hulahoot-industry-section .hulahoot-industry-search {
    max-width: 720px;
    margin: 0 auto 30px;
    position: relative;
}
.hulahoot-industry-section .hulahoot-industry-search input {
    width: 100%;
    padding: 14px 20px 14px 44px;
    border: 2px solid #000;
    border-radius: 999px;
    font-size: 16px;
}
.hulahoot-industry-section .hulahoot-industry-search input:focus {
    outline: none;
    border-color: #000;
    box-shadow: 0 0 0 3px rgba(0,0,0,.08);
}
.hulahoot-industry-section .hulahoot-industry-search .fa {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #797979;
}
.hulahoot-industry-section .hulahoot-industry-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
}
.hulahoot-industry-section .hulahoot-industry-card {
    display: block;
    text-align: center;
    padding: 22px 14px;
    border: 1px solid #e2e2e2;
    border-radius: 10px;
    background: #fff;
    text-decoration: none;
    color: inherit;
    transition: box-shadow .15s ease, border-color .15s ease;
}
.hulahoot-industry-section .hulahoot-industry-card:hover {
    box-shadow: 0 4px 14px rgba(0,0,0,.08);
    border-color: #000;
    text-decoration: none;
    color: inherit;
}
.hulahoot-industry-section .hulahoot-industry-card-thumb {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 12px;
    display: block;
    background: #f0f0f0;
}
.hulahoot-industry-section .hulahoot-industry-card-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f0f0f0;
    color: #000;
    font-size: 22px;
}
.hulahoot-industry-section .hulahoot-industry-card-name {
    font-weight: 600;
    font-size: 15px;
    color: #000;
}
.hulahoot-industry-section .hulahoot-industry-empty {
    text-align: center;
    color: #797979;
    padding: 40px 20px;
}
.hulahoot-industry-section .hulahoot-industry-end {
    border-bottom: 1px solid #e2e2e2;
    margin: 28px 0 0;
}
@media (max-width: 600px) {
    .hulahoot-industry-section .hulahoot-marketplace-hero h1 {
        font-size: 24px;
    }
    .hulahoot-industry-section .hulahoot-marketplace-hero p {
        font-size: 14px;
    }
    .hulahoot-industry-section .hulahoot-industry-search input {
        padding: 11px 16px 11px 40px;
        font-size: 15px;
    }
    .hulahoot-industry-section .hulahoot-industry-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .hulahoot-industry-section .hulahoot-industry-card {
        padding: 14px 8px;
    }
    .hulahoot-industry-section .hulahoot-industry-card-thumb,
    .hulahoot-industry-section .hulahoot-industry-card-icon {
        width: 44px;
        height: 44px;
        font-size: 18px;
    }
    .hulahoot-industry-section .hulahoot-industry-card-name {
        font-size: 13px;
    }
}
{/literal}
</style>

<div class="hulahoot-industry-section">
    <div class="hulahoot-marketplace-hero">
        <h1>{_p var='hulahoot_find_your_industry'}</h1>
        <p>{_p var='hulahoot_find_your_industry_subtitle'}</p>

        <div class="hulahoot-industry-search">
            <i class="fa fa-search" aria-hidden="true"></i>
            <input type="text" id="js_hulahoot_home_industry_search" placeholder="{_p var='hulahoot_search_industry_placeholder'}">
        </div>
    </div>

    {if empty($aHulahootIndustries)}
        <div class="hulahoot-industry-empty">{_p var='hulahoot_no_industries_available'}</div>
    {else}
        <div class="hulahoot-industry-grid" id="js_hulahoot_home_industry_grid">
            {foreach from=$aHulahootIndustries item=aIndustry}
                <a href="{$aIndustry.href}" class="hulahoot-industry-card" data-name="{$aIndustry.search_key}">
                    {if $aIndustry.thumbnail_url}
                        <img class="hulahoot-industry-card-thumb" src="{$aIndustry.thumbnail_url}" alt="">
                    {else}
                        <span class="hulahoot-industry-card-icon"><i class="fa {$aIndustry.display_icon}" aria-hidden="true"></i></span>
                    {/if}
                    <div class="hulahoot-industry-card-name">{$aIndustry.display_name}</div>
                </a>
            {/foreach}
        </div>
        <p class="hulahoot-industry-empty" id="js_hulahoot_home_industry_no_match" style="display:none;">{_p var='hulahoot_no_matching_industries'}</p>
    {/if}

    <div class="hulahoot-industry-end"></div>
</div>

<script>
{literal}
(function () {
    var searchInput = document.getElementById('js_hulahoot_home_industry_search');
    var grid = document.getElementById('js_hulahoot_home_industry_grid');
    if (!searchInput || !grid) {
        return;
    }
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.hulahoot-industry-card'));
    var noMatch = document.getElementById('js_hulahoot_home_industry_no_match');

    searchInput.addEventListener('input', function () {
        var query = searchInput.value.trim().toLowerCase();
        var visibleCount = 0;

        cards.forEach(function (card) {
            var matches = query === '' || card.getAttribute('data-name').indexOf(query) !== -1;
            card.style.display = matches ? '' : 'none';
            if (matches) {
                visibleCount++;
            }
        });

        if (noMatch) {
            noMatch.style.display = visibleCount === 0 ? '' : 'none';
        }
    });
})();
{/literal}
</script>
