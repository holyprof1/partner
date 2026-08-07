<?php

namespace Apps\Hulahoot\Installation\Database;

use Core\App\Install\Database\Field;
use Core\App\Install\Database\Table;

/**
 * hulahoot_subscription_package
 *
 * Companion overlay for a native Core_Subscriptions subscribe_package row
 * - never a replacement. package_id is this table's own primary key (a
 * true 1:1 relationship, not a separate surrogate id), and is a soft
 * reference to subscribe_package.package_id: indexed, never a hard FK
 * constraint, matching every existing Hulahoot table's convention. No
 * price, title, currency, or billing-period column exists here - those
 * stay exclusively in subscribe_package, edited only in Core Subscriptions'
 * own AdminCP. A package with no row here has no Hulahoot-specific
 * configuration at all (unlimited limits, no presentation overrides), not
 * an error state - see docs/PHASE_2_SUBSCRIPTION.md.
 *
 * purchase_limit / campaign_limit are deliberately generic (not
 * "max_active_promotions") so future verticals (Advertising Hub, HHire,
 * Government, HG DIPs) can reuse these same columns instead of each
 * inventing its own "how many things can I create" field.
 *
 * subtitle/description/badge_text/image/accent_color/button_text/ordering
 * are presentation-only fields for the public Industry/Package browse
 * page (Phase 2) - never consumed by billing logic, never shown in
 * Core Subscriptions' own AdminCP.
 *
 * @package Apps\Hulahoot\Installation\Database
 */
class SubscriptionPackage extends Table
{
    protected function setTableName()
    {
        $this->_table_name = 'hulahoot_subscription_package';
    }

    protected function setFieldParams()
    {
        $this->_aFieldParams = [
            // = subscribe_package.package_id. Not auto-increment - this
            // row's identity is the native package's identity.
            'package_id' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL',
                Field::FIELD_PARAM_PRIMARY_KEY => true,
            ],
            // Short marketing subtitle shown under the native title on the
            // public browse page - e.g. "Best for solo creators".
            'subtitle' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // Longer marketing copy for the package card/detail view.
            // Independent of subscribe_package.description (the native
            // package's own admin-facing description) - this one is
            // public-facing and Hulahoot-authored.
            'description' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_TEXT,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // e.g. "Most Popular", "Best Value" - blank shows no badge.
            'badge_text' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 50,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // Stored path/URL, same convention as hulahoot_industry's
            // banner/thumbnail columns.
            'image' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 255,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // Optional hex color for card accents/highlighting. Blank =
            // theme default.
            'accent_color' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 20,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // Blank falls back to a generic "Choose Plan" phrase at
            // render time - never stored as a hardcoded default here.
            'button_text' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_VARCHAR,
                Field::FIELD_PARAM_TYPE_VALUE => 50,
                Field::FIELD_PARAM_OTHER => 'NULL',
            ],
            // Display order among packages within the same Industry.
            'ordering' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            // NULL = unlimited.
            'purchase_limit' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            // NULL = unlimited. Distinct from purchase_limit - e.g. a
            // "campaign" may bundle several promotions under one limit.
            'campaign_limit' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            // NULL = unlimited.
            'posting_limit_per_day' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            // NULL = unlimited.
            'posting_limit_per_month' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED DEFAULT NULL',
            ],
            // Configured amount only - no ledger, no earn/spend. Phase 2
            // scope is storage and display; Credits logic is a later
            // phase entirely.
            'monthly_credits' => [
                Field::FIELD_PARAM_TYPE => Field::TYPE_INT,
                Field::FIELD_PARAM_TYPE_VALUE => 10,
                Field::FIELD_PARAM_OTHER => 'UNSIGNED NOT NULL DEFAULT \'0\'',
            ],
            // A kill switch for Hulahoot's rules specifically, independent
            // of the native package's own is_active - lets an admin
            // disable Hulahoot-side enforcement without touching the
            // phpFox package record.
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
            'is_active' => ['is_active'],
            'ordering' => ['ordering'],
        ];
    }
}
