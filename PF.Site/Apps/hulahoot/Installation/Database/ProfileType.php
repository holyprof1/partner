<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_profile_type
 *
 * Admin-managed top-level identity types. Two classifications
 * (is_individual): individual (Personal, Creator, Influencer, Celebrity,
 * Public Figure, ...) - selectable at registration - and organization
 * (Business, Organization, ...) - only creatable after registration.
 * See docs/DatabaseSchema.md for the full design rationale.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class ProfileType extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_profile_type';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'profile_type_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
                Field::FIELD_PARAM_AUTO_INCREMENT => true,
            ],
            // Phrase var_name, resolved via _p() at render time.
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
            'description' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'DEFAULT NULL',
            ],
            'icon' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 100,
                Field::FIELD_PARAM_OTHER => 'DEFAULT NULL',
            ],
            // Whether choosing this type forces a Category selection.
            'requires_category' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            // Exactly one row should have this set; enforced in the Profile Service, not the DB.
            'is_default' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            // 1 = an individual-classification type (Personal, Creator,
            // Influencer, Celebrity, Public Figure, ...) - selectable at
            // registration. 0 = an organization/entity type (Business,
            // Organization, ...) - never offered during registration,
            // only through the post-registration create-profile flow.
            'is_individual' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            'is_active' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'1\'',
            ],
            // 1 (default) = a Master Account can create another profile of
            // this type themselves via /my-profiles/create. 0 = this type
            // can only be assigned some other way (registration's own
            // is_individual gate, an admin override, a future entitlement
            // flow, etc.) - never offered in the self-service create form.
            // Defaulting to 1 preserves today's behavior for every existing
            // row (every active type is currently creatable there) - this
            // column only changes anything once an administrator explicitly
            // flips a type off.
            'is_user_creatable' => [
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
            'is_active_ordering' => ['is_active', 'ordering'],
            'name_url' => ['name_url'],
        ];
    }
}
