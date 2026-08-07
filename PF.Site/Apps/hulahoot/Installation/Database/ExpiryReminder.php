<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_expiry_reminder
 *
 * One row per native subscribe_purchase row that has entered its
 * post-expiry grace period (Marketplace::GRACE_PERIOD_DAYS) - tracks how
 * many renewal reminder emails have gone out so Service\ExpiryReminders
 * never sends more than the configured cap, and never sends the same
 * reminder twice on the same day if the cron happens to run more than
 * once. purchase_id is a soft reference to subscribe_purchase.purchase_id
 * (never a hard FK, matching every other Hulahoot table's convention) -
 * no row here means no reminder has been sent yet for that purchase.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class ExpiryReminder extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_expiry_reminder';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'purchase_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
            ],
            'reminder_count' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 3,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            'last_sent' => [
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
