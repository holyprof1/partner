<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_subscription_package_industry
 *
 * Junction table: which Industries a subscription package is sold under.
 * Many-to-many: a package may span industries, and an industry may offer
 * several packages. Replaces the earlier
 * hulahoot_subscription_package_category (which pointed at
 * hulahoot_profile_category, a registration-only concept) - packages are
 * now scoped to hulahoot_industry instead. See Industry.php's docblock
 * and docs/PHASE_2_SUBSCRIPTION.md for why that separation exists.
 *
 * A package with zero rows here is available under every industry
 * (unrestricted), not under none - same convention the retired category
 * junction used.
 *
 * Both package_id and industry_id are soft references (indexed, no hard
 * FK constraint) to subscribe_package.package_id and
 * hulahoot_industry.industry_id respectively - matching every existing
 * Hulahoot table's convention.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SubscriptionPackageIndustry extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_subscription_package_industry';
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
            'industry_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
        ];
    }

    protected function setKeys()
    {
        // Non-unique by necessity (native Table builder only ever emits
        // plain KEY - see the retired package_category migration's
        // docblock for the source-verified explanation). Uniqueness of
        // (package_id, industry_id) is enforced in the service layer
        // before insert.
        $this->_key = [
            'package_industry' => ['package_id', 'industry_id'],
            'industry_id' => ['industry_id'],
        ];
    }
}
