<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_industry
 *
 * The post-login marketplace a user browses before purchasing a
 * subscription package - deliberately independent of
 * hulahoot_profile_type / hulahoot_profile_category, which exist only for
 * registration/profile classification. An Industry is not "what a user's
 * profile is"; it's "which storefront the user is currently browsing."
 * See docs/PHASE_2_SUBSCRIPTION.md for the full reasoning behind keeping
 * these two concerns on separate tables.
 *
 * No profile_type_id, no parent_id: Industry is a flat, top-level list -
 * registration can evolve independently of it, and vice versa.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class Industry extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_industry';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'industry_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
                Field::FIELD_PARAM_AUTO_INCREMENT => true,
            ],
            'name' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 100,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            // URL-safe identifier for the industry's public page
            // (/industry/{slug}). Uniqueness is enforced in the service
            // layer before insert - same convention as every other
            // Hulahoot table's non-unique native KEY (see
            // SubscriptionPackageCategory's docblock for why).
            'slug' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 100,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            'description' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TEXT,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // Stored path/URL, not a binary blob - matches how every
            // other image field in this codebase (profile photos, etc.)
            // is handled: upload writes to file storage, this column
            // just points at it.
            'banner' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            'thumbnail' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // A small icon (font-icon class or emoji), independent of the
            // thumbnail photo - e.g. for compact list/nav rendering where
            // a full thumbnail image would be too heavy.
            'icon' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 100,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            'sort_order' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            'is_active' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'1\'',
            ],
            'created_at' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            'updated_at' => [
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
            'sort_order' => ['sort_order'],
            'slug' => ['slug'],
        ];
    }
}
