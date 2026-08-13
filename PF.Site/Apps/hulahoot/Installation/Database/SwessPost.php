<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_post
 *
 * Structural shell only - deliberately not written to by anything yet.
 * No composer, no feed hook, and no publishing engine exist in this
 * phase (all explicitly deferred to the future Hulahoot.com build); this
 * table exists so that foundation has a place to land without a later
 * schema redesign. feed_id is nullable and unused today - the eventual
 * link to a real phpfox_feed row once posting is actually built.
 *
 * distribution_target_type / _value / _label: the flat, provider-neutral
 * shape agreed for future location targeting. type is one of
 * 'site_wide' | 'continent' | 'country' | 'state' | 'city'; value's
 * meaning depends on type (null for site_wide, a short continent code,
 * phpfox_country.country_iso, phpfox_country_child.child_id, or an
 * opaque city value/id the future Hulahoot Location Service defines) -
 * see docs on why this is one flexible column pair rather than one
 * FK column per level. label is a composer-supplied display string,
 * stored verbatim, never resolved or validated here.
 *
 * status is the publishing/status structure requested for this phase -
 * a plain string, not an enum, so the full lifecycle can be supported
 * without a column change: 'draft' | 'pending' | 'approved' |
 * 'scheduled' | 'published' | 'failed' | 'rejected' | 'archived'.
 * No code transitions it yet; 'draft' is simply what a row would start
 * as once something creates one.
 *
 * identity_type/identity_id mirror hulahoot_swess_approved_identity's
 * shape (soft reference, 'self' | 'page') - which identity this post
 * would be published as. tag_id is a soft reference to
 * hulahoot_swess_tag - the disclosure tag this post would carry.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessPost extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_post';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'swess_post_id' => [
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
            // Unused until posting exists - soft reference to
            // phpfox_feed.feed_id once a real post is created.
            'feed_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
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
            'tag_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            'distribution_target_type' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 20,
                Field::FIELD_PARAM_OTHER => 'NOT NULL DEFAULT \'site_wide\'',
            ],
            'distribution_target_value' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            'distribution_target_label' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // Full lifecycle - see class docblock. Plain string, nothing
            // transitions this yet.
            'status' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 20,
                Field::FIELD_PARAM_OTHER => 'NOT NULL DEFAULT \'draft\'',
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
            'status' => ['status'],
        ];
    }
}
