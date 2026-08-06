<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_subscription_package_category
 *
 * Junction table: which Hulahoot Profile Categories ("Industries" - the
 * Restaurant/Retail/Creator/... rows already built under the Business
 * and Organization Profile Types) a subscription package is intended
 * for. Many-to-many: a plan may span industries, and an industry may
 * offer several plans.
 *
 * A package with zero rows here is available to every industry
 * (unrestricted), not to none - a package only becomes industry-scoped
 * once an admin explicitly links it to at least one category. See
 * docs/PHASE_2_SUBSCRIPTION.md.
 *
 * Both package_id and category_id are soft references (indexed, no hard
 * FK constraint) to subscribe_package.package_id and
 * hulahoot_profile_category.category_id respectively - matching every
 * existing Hulahoot table's convention.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SubscriptionPackageCategory extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_subscription_package_category';
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
            'category_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
        ];
    }

    protected function setKeys()
    {
        // Non-unique, by necessity: Core\App\Install\Database\Table's
        // createTable() (confirmed by reading the mysql driver directly)
        // only ever emits plain KEY, never UNIQUE KEY - there is no native
        // mechanism here to enforce (package_id, category_id) uniqueness
        // at the schema level. Uniqueness is enforced in the service
        // layer instead, before insert - the same convention this app
        // already uses for hulahoot_profile_type.is_default ("exactly one
        // row should have this set; enforced in the Profile Service, not
        // the DB").
        $this->_key = [
            'package_category' => ['package_id', 'category_id'],
            'category_id' => ['category_id'],
        ];
    }
}
