<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_credit_transaction
 *
 * Append-only ledger of every credit movement - the same "read for
 * debugging/proof, never queried by structured filters on its content"
 * role hulahoot_swess_audit_log already plays for whitelist/identity/tag
 * events, just scoped to credits specifically so a credit history can be
 * shown per user without filtering the (much noisier) general audit log
 * by string-matching action names.
 *
 * type is one of:
 * - 'reserve'  - a post submission reserved this many credits
 * - 'release'  - a reservation was returned to available (post
 *                cancelled, rejected, or failed to publish)
 * - 'consume'  - a reservation was finalized as spent (post reached
 *                'published' status)
 * - 'bonus_grant' / 'bonus_revoke' - an admin correction
 *   (Service\CreditLedger::adjustBonus())
 *
 * amount is always UNSIGNED (the direction is carried by type, never by
 * a signed amount) - matches this table's own append-only, never-edited
 * nature: every row is a plain historical fact, not a running total to
 * recompute from signed deltas.
 *
 * swess_post_id is nullable - only set for reserve/release/consume rows;
 * a bonus_grant/bonus_revoke row has no associated post.
 *
 * Both swess_post_id and actor_user_id are soft references, matching
 * every existing Hulahoot table's convention - no hard FK.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessCreditTransaction extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_credit_transaction';
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
            'user_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'swess_post_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            'type' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 20,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            'amount' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            // Set for bonus_grant/bonus_revoke (the admin who acted);
            // null for a system-driven reserve/release/consume row.
            'actor_user_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            // Required (service-layer enforced) for bonus_grant/bonus_revoke,
            // optional elsewhere - e.g. 'rejected', 'cancelled', 'publish_failed'
            // for a release row, so the ledger itself explains why credit moved
            // without needing a join back to the post's own status history.
            'note' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NULL',
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
            'swess_post_id' => ['swess_post_id'],
            'type' => ['type'],
            'created' => ['created'],
        ];
    }
}
