<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_post
 *
 * A real SWESS post record, per the SWESS UI/UX spec's composer
 * workflow (Identity -> Content -> Tag -> Target -> Schedule -> Review
 * -> Submit) - Service\Swess owns the full Draft/Pending/Approved/
 * Scheduled/Published/Failed/Rejected/Archived lifecycle over this
 * table. Still deliberately self-contained: nothing here creates a real
 * phpfox_feed row or touches the native Feed. "Published" means SWESS
 * itself considers the post live, tracked entirely in this table -
 * actually distributing it onto hulahoot.com (a real feed_id, a real
 * publish call) is still the explicitly-deferred next phase, per
 * docs/HULAHOOT_INTEGRATION.md. feed_id stays nullable and unused until
 * that phase links a real phpfox_feed row here.
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
            // Plain text only in this phase - the spec's "normal post
            // composer (text, media, links) exactly as it exists
            // elsewhere" is native Feed authoring, which this phase does
            // not integrate with (see class docblock). A future phase
            // that wires this to a real phpfox_feed row inherits media/
            // links from that native flow rather than duplicating it here.
            'content' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TEXT,
                Field::FIELD_PARAM_OTHER => 'NULL',
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
            // 'draft' | 'pending' | 'approved' | 'scheduled' | 'published'
            // | 'failed' | 'rejected' | 'archived' - see class docblock
            // and Service\Swess's status-transition methods.
            'status' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 20,
                Field::FIELD_PARAM_OTHER => 'NOT NULL DEFAULT \'draft\'',
            ],
            // Set only when "Schedule for Later" is chosen; null for
            // Publish Now. A unix timestamp, not a date+timezone pair -
            // the spec's timezone display is sourced from the publisher's
            // existing account timezone at render time, not stored here.
            'scheduled_at' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            // Admin's stated reason when status = 'rejected' - shown back
            // to the publisher on the post detail view, per spec.
            'rejection_reason' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NULL',
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
            // Not consumed by anything yet (no scheduler exists), but a
            // future one would filter exactly this way - cheap to index
            // now rather than as a later migration.
            'scheduled_at' => ['scheduled_at'],
        ];
    }
}
