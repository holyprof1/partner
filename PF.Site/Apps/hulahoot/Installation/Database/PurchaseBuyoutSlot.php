<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_purchase_buyout_slot
 *
 * One row per completed purchase row that Service\PurchaseFlow::
 * expandCompletedBuyout() has created on behalf of one aggregated buy-out
 * anchor (hulahoot_purchase_buyout.purchase_id = buyout_purchase_id
 * here). Exists purely so expansion can be resumed safely: instead of a
 * single all-or-nothing "create N-1 rows" loop, expandCompletedBuyout()
 * counts how many rows already exist here for a given buyout_purchase_id
 * and only creates the remaining deficit - so a crash, timeout, or any
 * other interruption partway through expansion is always safely
 * resumable on the next attempt (the lazy sweep on every /industry and
 * /find-your-industry page load, or the synchronous attempt at
 * completion time) without ever creating more than slot_count - 1 rows
 * total, no matter how many times or where expansion is interrupted and
 * retried.
 *
 * purchase_id here is the newly-created completed subscribe_purchase.
 * purchase_id for that one slot - a soft reference, never a hard FK,
 * matching every other Hulahoot table's convention.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class PurchaseBuyoutSlot extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_purchase_buyout_slot';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL AUTO_INCREMENT',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
            ],
            // = hulahoot_purchase_buyout.purchase_id (the anchor).
            'buyout_purchase_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            // = subscribe_purchase.purchase_id (the created slot itself).
            'purchase_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
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
            'buyout_purchase_id' => ['buyout_purchase_id'],
        ];
    }
}
