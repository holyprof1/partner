<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_post_mention
 *
 * Which real accounts a SWESS post's content @mentions - resolved and
 * stored server-side at save time (Service\Swess::syncMentionsForPost())
 * by matching @username tokens against real usernames, the same exact-
 * match lookup convention Service\Swess::findUserByUsernameOrEmail()
 * already uses for the AdminCP whitelist form. A junction table, not a
 * text column, so a mention is a real, queryable reference to an actual
 * account rather than an unverified string in the post body.
 *
 * swess_post_id / mentioned_user_id are soft references (indexed, no hard
 * FK), matching every existing Hulahoot table's convention.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessPostMention extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_post_mention';
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
            'swess_post_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
            ],
            'mentioned_user_id' => [
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
            'swess_post_id' => ['swess_post_id'],
            'mentioned_user_id' => ['mentioned_user_id'],
        ];
    }
}
