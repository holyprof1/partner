<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_identity_tag
 *
 * Junction: which disclosure tag(s) (hulahoot_swess_tag) an admin has
 * allowed for a specific approved identity (hulahoot_swess_approved_
 * identity) - a many-to-many, not a single column on either side,
 * because one identity may be allowed more than one tag (e.g. a Page
 * that sometimes posts "Sponsored" and sometimes "Affiliate"). is_default
 * marks which one applies when only one is configured, or which one a
 * future composer pre-selects when several are allowed.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessIdentityTag extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_identity_tag';
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
            'approved_identity_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'tag_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'is_default' => [
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
            'approved_identity_id' => ['approved_identity_id'],
            'tag_id' => ['tag_id'],
        ];
    }
}
