<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_approved_identity
 *
 * Which specific identity (or identities) a whitelisted user may
 * actually post SWESS content as. Deliberately narrower than
 * hulahoot_swess_whitelist.post_as_business: that flag says "this user
 * may act as some Page," this table says "specifically these Pages, and
 * no others" - a user who manages five native phpFox Pages does not
 * automatically get SWESS rights on all five just because
 * post_as_business = 1.
 *
 * identity_type is deliberately a short string ('self' | 'page'), not a
 * hard-coded pair of nullable columns (profile_id, page_id) - keeps the
 * table extensible to a future identity type without a schema change,
 * matching the same reasoning already applied to the SWESS distribution
 * target on hulahoot_swess_post.
 *
 * identity_id means different things depending on identity_type:
 * 'self' -> hulahoot_profile.profile_id (the user's own profile - see
 * Service\Profile), 'page' -> the native phpfox_pages.page_id. Both are
 * soft references (indexed, no hard FK), matching every existing
 * Hulahoot table's convention - and matching the fact that 'page' here
 * points at a table this app has never owned or modified.
 *
 * Disclosure tags attach here, not on hulahoot_swess_whitelist - see
 * hulahoot_swess_identity_tag - because the same user's "self" and
 * "business" identities may legitimately need different tags (e.g. a
 * staff member's own posts vs. the business Page they also manage).
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessApprovedIdentity extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_approved_identity';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'approved_identity_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
                Field::FIELD_PARAM_AUTO_INCREMENT => true,
            ],
            'whitelist_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'user_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            // 'self' | 'page' - see class docblock.
            'identity_type' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 20,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            'identity_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'is_active' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'1\'',
            ],
            'approved_by' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
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
            'whitelist_id' => ['whitelist_id'],
            'user_id' => ['user_id'],
            'identity_lookup' => ['identity_type', 'identity_id'],
        ];
    }
}
