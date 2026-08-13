<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_whitelist
 *
 * The master SWESS gate for a Master Account, at most one row per
 * user_id (enforced in Service\Swess, not the database - same
 * convention as hulahoot_profile_type's "exactly one is_default" and
 * hulahoot_profile's "exactly one is_primary"; see those tables' own
 * docblocks for why this schema leans on the service layer instead of a
 * UNIQUE key).
 *
 * Deliberately does not carry disclosure tags, approved identities, or
 * targeting - those are per-identity (hulahoot_swess_approved_identity,
 * hulahoot_swess_identity_tag) or per-post (hulahoot_swess_post), not
 * per-user. This row only answers "is SWESS available to this user at
 * all, and in which of the two broad modes."
 *
 * post_as_self / post_as_business intentionally live here rather than in
 * a separate companion table - they are 1:1 with the whitelist row
 * itself, not an independent entity, so a second table would be an
 * unused join for something that is never queried except alongside its
 * own whitelist row.
 *
 * user_id is a soft reference (indexed, no hard FK) to phpfox_user.user_id,
 * matching every existing Hulahoot table's convention.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class Swess extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_whitelist';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'whitelist_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
                Field::FIELD_PARAM_AUTO_INCREMENT => true,
            ],
            'user_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            // Master switch - SWESS unavailable to this user at all when 0,
            // regardless of the two flags below or any approved identity.
            'is_enabled' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            // May this user post SWESS content as themselves (their own
            // hulahoot_profile identity)?
            'post_as_self' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            // May this user post SWESS content as a Page they manage?
            // Which specific Page(s) is a separate, narrower question -
            // see hulahoot_swess_approved_identity. This flag alone grants
            // nothing.
            'post_as_business' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            // phpfox_user.user_id of the admin who enabled this - display
            // only, never used in any permission check.
            'enabled_by' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
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
            'user_id' => ['user_id'],
            'is_enabled' => ['is_enabled'],
        ];
    }
}
