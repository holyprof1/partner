<?php

namespace Apps\Hulahoot\Block;

defined('PHPFOX') or exit('NO DICE!');

/**
 * Native block version of the Industry search grid, for placement above
 * the native Feed block (block_id 267, m_connection core.index-member) on
 * the member homepage - a sibling in the same block location, not a page
 * override. Reuses the exact same query and markup as
 * views/find-your-industry.html (the standalone /find-your-industry
 * route, untouched); the HTML is built here in PHP rather than through
 * that Twig template because native blocks render through phpFox's
 * legacy Smarty-style template engine (views/block/*.html.php), a
 * different pipeline. Card URLs are pre-resolved with Phpfox_Url here -
 * the same call the `url()` Twig function itself makes - so the block
 * template only ever outputs already-safe strings.
 */
class Industry extends \Phpfox_Component
{
    public function process()
    {
        $service = new \Apps\Hulahoot\Service\Marketplace();
        $uploadService = new \Apps\Hulahoot\Service\ImageUpload();

        $aIndustries = $service->getActiveIndustries();

        foreach ($aIndustries as &$aIndustry) {
            $aIndustry['href'] = \Phpfox_Url::instance()->makeUrl('/industry', ['slug' => $aIndustry['slug']]);
            $aIndustry['thumbnail_url'] = $uploadService->resolveUrl($aIndustry['thumbnail']);
            $aIndustry['display_name'] = htmlspecialchars(_p($aIndustry['name']), ENT_QUOTES, 'UTF-8');
            $aIndustry['search_key'] = strtolower($aIndustry['display_name']);
            $aIndustry['display_icon'] = !empty($aIndustry['icon']) ? $aIndustry['icon'] : 'fa-briefcase';
        }
        unset($aIndustry);

        $this->template()->assign([
            'aHulahootIndustries' => $aIndustries,
        ]);

        return 'block';
    }
}
