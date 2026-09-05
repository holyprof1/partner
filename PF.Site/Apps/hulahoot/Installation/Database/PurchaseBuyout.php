<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_purchase_buyout
 *
 * Marks one real subscribe_purchase row as an aggregated "Buy Out
 * Remaining Slots" purchase for a PAID package - purchase_id is this
 * table's own primary key (soft reference to subscribe_purchase.purchase_id,
 * same 1:1-by-native-id convention as hulahoot_subscription_package's own
 * package_id), never a hard FK, matching every other Hulahoot table.
 *
 * Why this exists: native Core Subscriptions has no concept of "one
 * checkout, N slots" - a real gateway (Stripe) charges and completes
 * exactly one purchase row per checkout. Service\PurchaseFlow::
 * buyOutRemainingSlots() works around this for a paid package by
 * creating ONE purchase row priced at slot_count x the package's unit
 * price, recording that intent here, and overriding the actual gateway
 * checkout amount to match (see hooks/subscribe.component_controller_
 * register__1.php - no native file touched). Once that one purchase is
 * confirmed completed by the real gateway (native Callback.php,
 * unmodified), Service\PurchaseFlow::expandCompletedBuyout() - triggered
 * both synchronously from hooks/subscribe.service_purchase_process_
 * update_pre_log.php and lazily on every /industry, /find-your-industry
 * page load, same established pattern as Service\Swess's own lazy sync -
 * creates the remaining (slot_count - 1) completed purchase rows
 * directly, the same direct-DB-write completion PurchaseFlow::
 * completeAsHulahoot() already uses for the free/admin path. Writing
 * those extra rows directly (never through native Purchase\Process::
 * update()) is deliberate: that method unconditionally cancels every
 * OTHER completed purchase the same user holds the moment one completes -
 * exactly the side effect a multi-slot buy-out must not trigger against
 * its own other slots.
 *
 * is_expanded flips to 1 only after every expansion row this buy-out
 * needs has been created (tracked in hulahoot_purchase_buyout_slot, see
 * that table's own docblock for why expansion is resumable/idempotent
 * rather than a single all-or-nothing loop) - so expandCompletedBuyout()
 * never double-creates rows on a later retry for the same purchase, and
 * a retry after a crash mid-expansion picks up exactly where it left off.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class PurchaseBuyout extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_purchase_buyout';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            // = subscribe_purchase.purchase_id - the one real, gateway-
            // charged purchase row this buy-out is anchored to.
            'purchase_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
            ],
            'user_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'package_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            // Total slots this one aggregated purchase represents (>= 1).
            // The anchor purchase row itself is slot 1 of slot_count -
            // expansion only ever needs to create (slot_count - 1) more.
            'slot_count' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'is_expanded' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
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
            'user_id' => ['user_id'],
            'is_expanded' => ['is_expanded'],
        ];
    }
}
