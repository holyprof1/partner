<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_profile_category
 *
 * Category AND Subcategory for a Profile Type, as a single self-referencing
 * tree (parent_id = 0 -> Category, parent_id = another row's category_id ->
 * Subcategory of it). Deliberately shaped to match phpFox's own existing
 * Core_Service_Systems_Category_Category convention. See
 * docs/DatabaseSchema.md for the full rationale.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class ProfileCategory extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_profile_category';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'category_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
                Field::FIELD_PARAM_AUTO_INCREMENT => true,
            ],
            // Denormalized onto every row (including subcategories) so
            // type-scoped queries never need a recursive parent walk.
            'profile_type_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            // 0 = this row is a Category; otherwise the category_id of the
            // parent Category, making this row a Subcategory.
            'parent_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            'name' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 100,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            'name_url' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 100,
                Field::FIELD_PARAM_OTHER => 'DEFAULT NULL',
            ],
            'is_active' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'1\'',
            ],
            'ordering' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_SMALLINT,
                Field::FIELD_PARAM_TYPE_VALUE => 4,
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
            // "Get active children of node X, in order" - the exact query
            // pattern Core_Service_Systems_Category_Category runs.
            'parent_active_ordering' => ['parent_id', 'is_active', 'ordering'],
            // "Get all top-level categories for Profile Type X".
            'profile_type_parent' => ['profile_type_id', 'parent_id'],
        ];
    }
}
