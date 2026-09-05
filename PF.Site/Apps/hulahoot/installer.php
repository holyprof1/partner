<?php
// Runs once per install/upgrade, after App::processInstall() has already
// created/upgraded hulahoot_profile_type, hulahoot_profile_category, and
// hulahoot_profile (see Core\App\App::processInstall(), which requires
// this file iff it exists - same convention PF.Site/Apps/core/installer.php
// uses for its own post-table install steps).
$installer = new Core\App\Installer();
$installer->onInstall(function () {
    \Apps\Hulahoot\Installation\Seed\ProfileTypeSeeder::run();
    // Temporary starter categories for Business/Organization - see the
    // class docblock for why this specific set is a placeholder, not a
    // final decision.
    \Apps\Hulahoot\Installation\Seed\CategorySeeder::run();
    // Existing users predate the registration hook - MigrationPlan.md
    // step 7. Must run after ProfileTypeSeeder (needs the is_default
    // type to already exist).
    \Apps\Hulahoot\Installation\Seed\UserBackfillSeeder::run();
});
