<?php

namespace Apps\Hulahoot\Ajax;

use Phpfox;
use Phpfox_Ajax;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

class Ajax extends Phpfox_Ajax
{
    /**
     * Drag-and-drop reordering for the Profile Types list - same
     * Core_drag / core.process:updateOrdering mechanism every other
     * first-party AdminCP list (Photos, Pages, Videos) already uses.
     */
    public function typeOrdering()
    {
        Phpfox::isAdmin(true);
        $aVals = $this->get('val');

        Phpfox::getService('core.process')->updateOrdering([
            'table' => 'hulahoot_profile_type',
            'key' => 'profile_type_id',
            'values' => $aVals['ordering'],
        ]);
    }

    /**
     * Drag-and-drop reordering for top-level Profile Categories within
     * one Profile Type. Sub-categories keep the existing manual Sort
     * Order field (Advanced Settings) for now - see conversation notes.
     */
    public function categoryOrdering()
    {
        Phpfox::isAdmin(true);
        $aVals = $this->get('val');

        Phpfox::getService('core.process')->updateOrdering([
            'table' => 'hulahoot_profile_category',
            'key' => 'category_id',
            'values' => $aVals['ordering'],
        ]);
    }
}
