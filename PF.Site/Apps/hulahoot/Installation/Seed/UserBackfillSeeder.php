<?php

namespace Apps\Hulahoot\Installation\Seed;

use Apps\Hulahoot\Service\Profile;

/**
 * Backfills a default Individual profile for every existing phpfox_user
 * that doesn't already have a hulahoot_profile row - see MigrationPlan.md
 * step 7. Needed because this app was installed against an existing user
 * base, not a fresh one: the registration hook only covers users who sign
 * up after the app is installed, so anyone already registered before that
 * point would otherwise be stuck with zero profiles indefinitely.
 *
 * Idempotent: safe to run on every deploy/upgrade, not just first install
 * (only inserts for users found via a LEFT JOIN ... IS NULL against
 * hulahoot_profile, so a user who already has one - from the registration
 * hook or otherwise - is never touched twice).
 *
 * @package Apps\Hulahoot\Installation\Seed
 */
class UserBackfillSeeder
{
    /**
     * @return void
     */
    public static function run()
    {
        $oProfile = new Profile();
        $iDefaultProfileTypeId = $oProfile->getDefaultProfileTypeId();

        if (!$iDefaultProfileTypeId) {
            // No is_default Profile Type configured yet (ProfileTypeSeeder
            // should always run first via installer.php, but this guards
            // against running the backfill in isolation, e.g. re-running
            // just this class by hand).
            return;
        }

        $aUserIds = (array)db()->select('user.user_id')
            ->from(':user', 'user')
            ->leftJoin(':hulahoot_profile', 'profile', 'profile.user_id = user.user_id')
            ->where('profile.profile_id IS NULL')
            ->execute('getSlaveRows');

        foreach ($aUserIds as $aRow) {
            try {
                $oProfile->create((int)$aRow['user_id'], $iDefaultProfileTypeId);
            } catch (\Exception $e) {
                \Phpfox::getLog('hulahoot.log')->error(
                    'Backfill failed to create a profile for existing user ' . $aRow['user_id']
                    . ': ' . $e->getMessage()
                );
            }
        }
    }
}
