<?php

namespace Apps\Hulahoot\Installation\Seed;

/**
 * Seeds the default Profile Types: five individual-classification types
 * (Personal, Creator, Influencer, Celebrity, Public Figure - selectable at
 * registration) and two organization types (Business, Organization -
 * only creatable after registration, through the profile management
 * system). Administrators can add more of either classification later
 * through the same hulahoot_profile_type table - nothing about which
 * types exist is hard-coded anywhere outside this seed data.
 *
 * name_url is the identity key row-matching runs against, not `name` -
 * the "individual" row predates this classification system (it used to
 * be the only individual type, labeled "Individual") and keeps its
 * original name_url/name so existing hulahoot_profile rows referencing
 * it are unaffected. Its user-facing label changed from "Individual" to
 * "Personal" purely via phrase.json text, not a var_name/name_url change.
 *
 * Idempotent: safe to run on every deploy/upgrade, not just first install.
 * A row that already exists (matched by name_url) has its classification
 * flags brought up to date rather than being skipped outright, so
 * installs seeded before is_individual existed still end up correct.
 *
 * @package Apps\Hulahoot\Installation\Seed
 */
class ProfileTypeSeeder
{
    /**
     * @return void
     */
    public static function run()
    {
        $aTypes = [
            // Individual classification - offered at registration.
            [
                'name' => 'hulahoot_profile_type_individual',
                'name_url' => 'individual',
                'requires_category' => 0,
                'is_default' => 1,
                'is_individual' => 1,
                'ordering' => 1,
            ],
            [
                'name' => 'hulahoot_profile_type_creator',
                'name_url' => 'creator',
                'requires_category' => 0,
                'is_default' => 0,
                'is_individual' => 1,
                'ordering' => 2,
            ],
            [
                'name' => 'hulahoot_profile_type_influencer',
                'name_url' => 'influencer',
                'requires_category' => 0,
                'is_default' => 0,
                'is_individual' => 1,
                'ordering' => 3,
            ],
            [
                'name' => 'hulahoot_profile_type_celebrity',
                'name_url' => 'celebrity',
                'requires_category' => 0,
                'is_default' => 0,
                'is_individual' => 1,
                'ordering' => 4,
            ],
            [
                'name' => 'hulahoot_profile_type_public_figure',
                'name_url' => 'public-figure',
                'requires_category' => 0,
                'is_default' => 0,
                'is_individual' => 1,
                'ordering' => 5,
            ],
            // Organization classification - never offered at registration.
            [
                'name' => 'hulahoot_profile_type_business',
                'name_url' => 'business',
                'requires_category' => 1,
                'is_default' => 0,
                'is_individual' => 0,
                'ordering' => 6,
            ],
            [
                'name' => 'hulahoot_profile_type_organization',
                'name_url' => 'organization',
                'requires_category' => 1,
                'is_default' => 0,
                'is_individual' => 0,
                'ordering' => 7,
            ],
        ];

        foreach ($aTypes as $aType) {
            $iExistingId = db()->select('profile_type_id')
                ->from(':hulahoot_profile_type')
                ->where(['name_url' => $aType['name_url']])
                ->execute('getSlaveField');

            if ($iExistingId) {
                db()->update(':hulahoot_profile_type', [
                    'requires_category' => $aType['requires_category'],
                    'is_default' => $aType['is_default'],
                    'is_individual' => $aType['is_individual'],
                    'ordering' => $aType['ordering'],
                ], ['profile_type_id' => (int)$iExistingId]);

                continue;
            }

            db()->insert(':hulahoot_profile_type', [
                'name' => $aType['name'],
                'name_url' => $aType['name_url'],
                'description' => null,
                'icon' => null,
                'requires_category' => $aType['requires_category'],
                'is_default' => $aType['is_default'],
                'is_individual' => $aType['is_individual'],
                'is_active' => 1,
                // Not touched by the update() branch above, same as
                // is_active - an administrator's later decision to turn
                // this off for a seeded type must survive re-running this
                // seeder on the next deploy, not get silently reverted.
                'is_user_creatable' => 1,
                'ordering' => $aType['ordering'],
                'created' => time(),
            ]);
        }
    }
}
