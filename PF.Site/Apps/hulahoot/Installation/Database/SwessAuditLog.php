<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_audit_log
 *
 * One row per SWESS configuration change or enforcement decision -
 * whitelist toggled, permission changed, identity approved/revoked, tag
 * assigned, or a canPostAs() check that was denied. Exists so "the
 * system enforces this" is something we can point at, not just assert -
 * see the User A/B/C verification requirement this whole feature was
 * scoped against.
 *
 * context is a JSON-encoded blob of whatever's relevant to that specific
 * action (old/new values, the identity checked, the reason denied) -
 * deliberately unstructured rather than a wide sparse column set, since
 * different actions need different context and this table is read for
 * debugging/proof, never queried by structured filters on its content.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessAuditLog extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_audit_log';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
                Field::FIELD_PARAM_AUTO_INCREMENT => true,
            ],
            // The user the action was performed on/by, depending on
            // 'action' - e.g. for 'whitelist_enabled' this is the
            // whitelisted user; for 'post_denied' this is the user who
            // was denied.
            'user_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            // 'self' | 'page' | null - only set for identity-level actions.
            'identity_type' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 20,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            'identity_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            // Short machine-readable action key, e.g. 'whitelist_enabled',
            // 'whitelist_disabled', 'permission_changed',
            // 'identity_approved', 'identity_revoked', 'tag_assigned',
            // 'post_check_allowed', 'post_check_denied'.
            'action' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 60,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            'context' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TEXT,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // phpfox_user.user_id of the admin/actor who caused this row,
            // null for system-generated rows (e.g. an enforcement check
            // with no human actor).
            'actor_user_id' => [
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
            'user_id' => ['user_id'],
            'action' => ['action'],
            'created' => ['created'],
        ];
    }
}
