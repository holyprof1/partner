<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_credit_account
 *
 * The materialized credit balance for one user - one row per user,
 * lazily created the first time Service\CreditLedger touches them (same
 * "no row = nothing configured yet, not an error" convention
 * hulahoot_swess_whitelist and hulahoot_subscription_package both already
 * use).
 *
 * Deliberately does NOT store the package-derived allocation itself -
 * that stays exactly what it already was, a live read through
 * Service\Entitlement::getActiveEntitlement()['monthly_credits'] (summed
 * across every active purchase, per that method's own merge rule). This
 * table only tracks CONSUMPTION against that live allocation:
 *
 *   available = (package allocation, read live) + bonus_credits - reserved - used
 *
 * bonus_credits is signed (not UNSIGNED, unlike every other numeric
 * column in this app) - the one deliberate exception, because an admin
 * "Correct credits" action per Milestone 2's spec must be able to both
 * grant extra credits beyond the package (positive) and revoke previously
 * granted bonus credits (negative) as a single running net adjustment,
 * not two separate unsigned accumulators to reconcile by hand.
 *
 * reserved / used are UNSIGNED and only ever move through
 * Service\CreditLedger's reserve()/consume()/release() - see that
 * class's own docblock for the exact state machine (AVAILABLE -> RESERVED
 * -> USED, with RELEASE returning a reservation to AVAILABLE on
 * cancellation/rejection/publish-failure).
 *
 * user_id is a soft reference to phpfox_user.user_id, matching every
 * existing Hulahoot table's convention - no hard FK.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessCreditAccount extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_credit_account';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'user_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
            ],
            // Signed - see class docblock. A net running total, adjusted
            // by Service\CreditLedger::adjustBonus() only.
            'bonus_credits' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 11,
                Field::FIELD_PARAM_OTHER => 'NOT NULL DEFAULT \'0\'',
            ],
            'reserved' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            'used' => [
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
        $this->_key = [];
    }
}
