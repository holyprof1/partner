<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_swess_post_media
 *
 * One row per photo attached to a SWESS post - Milestone 2's "media"
 * composer requirement. Ordered child table (same shape as
 * hulahoot_subscription_package_feature), not a fixed set of columns, so
 * a post can carry any number of photos.
 *
 * Deliberately image-only for this milestone: Service\ImageUpload.php is
 * the only real, already-working upload pattern in this codebase to reuse
 * (per the instruction to extend established patterns, not invent a
 * parallel architecture) - it has no video/transcoding pipeline behind it
 * anywhere in this app, and building one from scratch would be exactly
 * the kind of new parallel infrastructure that instruction rules out.
 * media_type is still a column (not assumed 'image' implicitly) so a
 * later milestone can add a real video pipeline as a pure additive change
 * once one actually exists to reuse.
 *
 * swess_post_id is a soft reference to hulahoot_swess_post.swess_post_id,
 * matching every existing Hulahoot table's convention - no hard FK.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SwessPostMedia extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_swess_post_media';
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
            // 'image' only today - see class docblock.
            'media_type' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 20,
                Field::FIELD_PARAM_OTHER => 'NOT NULL DEFAULT \'image\'',
            ],
            // Stored path as returned by Service\ImageUpload::upload() -
            // resolve with Service\ImageUpload::resolveUrl() for display,
            // same convention as every other stored-image column in this app.
            'file_path' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NOT NULL',
            ],
            'ordering' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
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
            'swess_post_id' => ['swess_post_id'],
        ];
    }
}
