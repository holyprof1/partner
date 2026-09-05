<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_expiry_reminder
 *
 * One row per native subscribe_purchase row that has EITHER entered its
 * pre-expiry reminder window (before expiry_date - see Service\
 * ExpiryReminders' own docblock for the "renewal process begins before
 * expiry" requirement) OR its post-expiry grace period (Marketplace::
 * getGracePeriodDays()) - tracks how many reminder emails have gone out
 * in EACH phase separately, so Service\ExpiryReminders never sends more
 * than the admin-configured cap for either one, and never sends the same
 * reminder twice on the same day if the cron happens to run more than
 * once. purchase_id is a soft reference to subscribe_purchase.purchase_id
 * (never a hard FK, matching every other Hulahoot table's convention) -
 * no row here means no reminder of either kind has been sent yet for
 * that purchase.
 *
 * reminder_count/last_sent (original columns, unchanged) track the
 * POST-expiry (grace period) phase. pre_expiry_reminder_count/
 * pre_expiry_last_sent (added alongside the pre-expiry feature) track
 * the separate BEFORE-expiry phase - a purchase can have sent reminders
 * in both phases over its lifetime, tracked independently since they
 * have independent admin-configured caps/cadences.
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
            // Post-expiry (grace period) phase.
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
            // Pre-expiry phase.
            'pre_expiry_reminder_count' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 3,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            'pre_expiry_last_sent' => [
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
