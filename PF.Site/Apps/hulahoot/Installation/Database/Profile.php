<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_profile
 *
 * The assignment of a Profile Type (+ optional Category/Subcategory) to a
 * user's profile. In Phase 1, exactly one row per user_id (enforced by the
 * Profile Service, not a DB constraint - see the note on the "user_id" key
 * below). profile_id is its own primary key, independent of user_id, so
 * Phase 2 multiple-profile support is a matter of the service layer
 * allowing more than one row per user_id - no schema change. See
 * docs/EntityRelationships.md for the full rationale.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class Profile extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_profile';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            // Deliberately NOT reused as/from user_id, so Phase 2 can have
            // multiple profile_id rows per user_id without a key change.
            'profile_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
                Field::FIELD_PARAM_AUTO_INCREMENT => true,
            ],
            // The owning phpFox account (phpfox_user.user_id). Untouched
            // core table - referenced here by convention only, no FK.
            'user_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'profile_type_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            // User-facing label shown on profile cards ("Joe's Pizza",
            // "Acme Rescue Org"). Optional - falls back to the Profile
            // Type's own display name when blank (see Service\Profile).
            'profile_name' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 100,
                Field::FIELD_PARAM_OTHER => 'DEFAULT NULL',
            ],
            // Null until the chosen type requires one.
            'category_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            'subcategory_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            // This profile's own picture/banner, decoupled from the Master
            // Account's phpfox_user/phpfox_user_field row. NULL means "never
            // customized" (renders as phpFox's default no-avatar/no-cover
            // placeholder), not "has a blank image" - the same NULL-means-
            // unset convention this table already uses for category_id/
            // subcategory_id. Shapes mirror phpfox_user.user_image+server_id
            // and phpfox_user_field.cover_photo+cover_photo_top exactly
            // (a filename pattern + server id; a photo_id + crop position),
            // so \Apps\Hulahoot\Service\ActiveIdentity can copy values
            // straight across in either direction with no translation.
            // See docs/ActiveProfileIdentity.md.
            'avatar_image' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 75,
                Field::FIELD_PARAM_OTHER => 'DEFAULT NULL',
            ],
            'avatar_server_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            'cover_photo_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            'cover_photo_top' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 5,
                Field::FIELD_PARAM_OTHER => 'DEFAULT NULL',
            ],
            // Phase 1: always 1 (one profile per user). Phase 2: flags
            // which of a user's several profiles is currently active.
            'is_primary' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'1\'',
            ],
            'is_active' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'1\'',
            ],
            'created' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            'updated' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
        ];
    }

    protected function setKeys()
    {
        $this->_key = [
            // NOTE: this is a plain KEY, not a UNIQUE KEY. phpFox's Table
            // DSL (Core\App\Install\Database\Table::createTable()) only
            // ever emits `KEY` - there is no UNIQUE-key facility anywhere
            // in this DSL, and no App in this codebase uses one. The
            // Phase 1 invariant "exactly one profile per user" is instead
            // enforced in \Apps\Hulahoot\Service\Profile::create() before
            // insert, the same way hulahoot_profile_type.is_default's
            // "exactly one default" invariant is enforced in the service
            // layer rather than the database. This keeps the schema
            // consistent with every other table in this codebase, none of
            // which use DB-level uniqueness constraints either.
            'user_id' => ['user_id'],
            'profile_type_id' => ['profile_type_id'],
            'category_id' => ['category_id'],
            'subcategory_id' => ['subcategory_id'],
            // Inert in Phase 1 (every user has exactly one row); becomes
            // the "find this user's active profile" lookup in Phase 2.
            'user_primary' => ['user_id', 'is_primary'],
        ];
    }
}
