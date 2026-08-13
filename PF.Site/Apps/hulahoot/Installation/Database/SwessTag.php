<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_tag
 *
 * Admin-managed catalog of disclosure tags (Promo, Advertisement,
 * Sponsored, Affiliate, ...) - the client clarified a SWESS post is a
 * normal feed post carrying one of these, not a separate post type. Same
 * shape/conventions as hulahoot_profile_type: admin CRUD, is_active
 * kill-switch, ordering for display.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessTag extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_tag';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'tag_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
                Field::FIELD_PARAM_AUTO_INCREMENT => true,
            ],
            // Plain display text (not a phrase var_name, unlike
            // hulahoot_profile_type.name) - disclosure tags are expected
            // to be freely admin-authored ("Sponsored", "Paid Partnership",
            // ...) rather than translated UI chrome.
            'name' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 100,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            'description' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            'is_active' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'1\'',
            ],
            'ordering' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            'created' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
        ];
    }

    protected function setKeys()
    {
        $this->_key = [
            'is_active' => ['is_active'],
            'ordering' => ['ordering'],
        ];
    }
}
