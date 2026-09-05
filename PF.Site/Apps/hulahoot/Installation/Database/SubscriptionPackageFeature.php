<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_subscription_package_feature
 *
 * The bullet-point feature list shown on a package card - an ordered
 * child table, not a fixed set of columns, so one package can have 3
 * features and another 18 with no layout or schema change. Admin actions
 * (Add / Remove / Reorder Feature) map directly to insert / delete /
 * ordering-update on this table - no separate "enable this bullet" flag,
 * since removing a row already means "this package doesn't have this
 * feature."
 *
 * package_id is a soft reference (indexed, no hard FK constraint) to
 * subscribe_package.package_id, matching every existing Hulahoot table's
 * convention.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SubscriptionPackageFeature extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_subscription_package_feature';
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
            'package_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'feature_text' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            'ordering' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
        ];
    }

    protected function setKeys()
    {
        $this->_key = [
            'package_id' => ['package_id'],
        ];
    }
}
