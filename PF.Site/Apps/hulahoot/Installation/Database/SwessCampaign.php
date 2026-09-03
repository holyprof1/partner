<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_campaign
 *
 * A publisher-owned grouping of SWESS posts, per Milestone 2's "campaign
 * association" requirement. Deliberately per-user (not shared/global) -
 * campaigns exist to let one publisher organize several related posts
 * under a single umbrella for later reporting, matching
 * hulahoot_subscription_package.campaign_limit's own existing meaning
 * ("how many campaigns may this user hold") - see Service\Campaign::create()
 * for where that cap is finally enforced (previously a configured number
 * with nothing counting against it - Entitlement::getActiveEntitlement()'s
 * own campaigns_used was hardcoded to 0).
 *
 * user_id is a soft reference to phpfox_user.user_id, matching every
 * existing Hulahoot table's convention - no hard FK.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessCampaign extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_campaign';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            'campaign_id' => [
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
            'name' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 150,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            'description' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TEXT,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            'is_active' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TINYINT,
                Field::FIELD_PARAM_TYPE_VALUE => 1,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'1\'',
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
            'is_active' => ['is_active'],
        ];
    }
}
