<?php

namespace Apps\Hulahoot\Installation\Seed;

/**
 * Seeds a TEMPORARY starter category set for Business and Organization,
 * so the Profile Management "create a profile" flow has real data to work
 * with during development. This is explicitly a placeholder, not a client
 * sign-off:
 *
 * The list below (Restaurant/Retail/Creator under Business, Charity/
 * School/Public Figure under Organization) is one reasonable reading of
 * the examples named in the original Hulahoot Blueprint ("Restaurant,
 * Retail, Charity, School, Creator, and Public Figure"), which never
 * specified which type each belongs under - flagged as an open question
 * in docs/ProfileManagementImplementationPlan.md §6. Nothing about the
 * data model assumes this particular mapping; it can be changed by
 * editing the rows below, or superseded entirely by an AdminCP category
 * management screen later, without any schema change either way.
 *
 * Idempotent, same pattern as ProfileTypeSeeder: safe to run on every
 * deploy/upgrade, checks name_url per type before inserting.
 *
 * @package Apps\Hulahoot\Installation\Seed
 */
class CategorySeeder
{
    /**
     * @return void
     */
    public static function run()
    {
        $aByType = [
            'business' => [
                ['name' => 'hulahoot_profile_category_restaurant', 'name_url' => 'restaurant', 'ordering' => 1],
                ['name' => 'hulahoot_profile_category_retail', 'name_url' => 'retail', 'ordering' => 2],
                ['name' => 'hulahoot_profile_category_creator', 'name_url' => 'creator', 'ordering' => 3],
            ],
            'organization' => [
                ['name' => 'hulahoot_profile_category_charity', 'name_url' => 'charity', 'ordering' => 1],
                ['name' => 'hulahoot_profile_category_school', 'name_url' => 'school', 'ordering' => 2],
                ['name' => 'hulahoot_profile_category_public_figure', 'name_url' => 'public-figure', 'ordering' => 3],
            ],
        ];

        foreach ($aByType as $sTypeUrl => $aCategories) {
            $iProfileTypeId = db()->select('profile_type_id')
                ->from(':hulahoot_profile_type')
                ->where(['name_url' => $sTypeUrl])
                ->execute('getSlaveField');

            if (!$iProfileTypeId) {
                // The type this category set depends on doesn't exist
                // (e.g. an administrator removed it) - skip rather than
                // insert orphaned categories.
                continue;
            }

            foreach ($aCategories as $aCategory) {
                $iExists = db()->select('COUNT(*)')
                    ->from(':hulahoot_profile_category')
                    ->where(['profile_type_id' => $iProfileTypeId, 'name_url' => $aCategory['name_url']])
                    ->execute('getSlaveField');

                if ($iExists) {
                    continue;
                }

                db()->insert(':hulahoot_profile_category', [
                    'profile_type_id' => $iProfileTypeId,
                    'parent_id' => 0,
                    'name' => $aCategory['name'],
                    'name_url' => $aCategory['name_url'],
                    'is_active' => 1,
                    'ordering' => $aCategory['ordering'],
                    'created' => time(),
                ]);
            }
        }
    }
}
