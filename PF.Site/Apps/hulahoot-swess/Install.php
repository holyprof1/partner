<?php

namespace Apps\HulahootSwess;

use Core\App;
use Phpfox;

/**
 * Class Install
 *
 * A thin App registration whose only job is to make SWESS appear as its
 * own entry in the AdminCP Apps page (/admincp/app/), the way "Hulahoot
 * Profiles" already does - see Core\App\App::processInstall() (PF.Src/
 * Core/App/App.php:514), which is what actually inserts the :apps row
 * every app card on that page is read from (confirmed by reading
 * PF.Base/module/admincp/include/service/apps/apps.class.php directly,
 * not assumed).
 *
 * Deliberately owns NOTHING of the SWESS foundation itself:
 * - $database is empty - every hulahoot_swess_* table already exists,
 *   created by the main Hulahoot app's own Install.php ($this->database
 *   there, resolved against \Apps\Hulahoot\Installation\Database\*).
 *   Declaring them again here would try to resolve them against
 *   \Apps\HulahootSwess\Installation\Database\* instead (wrong
 *   namespace) and serves no purpose - they're already installed.
 * - No new controllers, routes, or services - $admincp_menu below
 *   dispatches to the exact same 'hulahoot.swess-whitelist' /
 *   'hulahoot.swess-tag' / 'hulahoot.swess-audit' component names the
 *   main Hulahoot app's start.php already registers. Dispatch is
 *   resolved by that dot-notation string alone (Phpfox_Module::
 *   getComponent()), independent of which App declared the menu entry
 *   that points at it - confirmed by reading that resolution path
 *   before relying on it.
 * - alias is intentionally 'hulahoot', the SAME alias the main app
 *   already registers as module_id 'hulahoot' - not a new module.
 *   Core\App\App::processInstall()'s :module insert is a no-op the
 *   moment that module_id already exists ("if (!$iCnt) { insert }"),
 *   so this doesn't create or need a second module registration.
 *
 * In short: this file's only real effect is one new :apps row
 * (apps_id 'HulahootSwess', apps_name 'SWESS') and one admincp_route/
 * admincp_menu pointing at controllers that already exist and already
 * work. Nothing about the SWESS tables, Service\Swess, or the SWESS
 * AdminCP controllers changes because this file exists.
 *
 * @author  HolyProf
 * @version 1.0.0
 * @package Apps\HulahootSwess
 */
class Install extends App\App
{
    protected function setId()
    {
        $this->id = 'HulahootSwess';
    }

    protected function setSupportVersion()
    {
        $this->start_support_version = Phpfox::getVersion();
        $this->end_support_version = '';
    }

    protected function setAlias()
    {
        $this->alias = 'hulahoot';
    }

    protected function setName()
    {
        $this->name = 'SWESS';
    }

    protected function setVersion()
    {
        $this->version = '1.0.0';
    }

    protected function setPhrase()
    {
        $this->phrase = [];
    }

    protected function setSettings()
    {
        // No settings of its own - SWESS's admin-configurable rules
        // (whitelist, permissions, tags) already live in the
        // hulahoot_swess_* tables via Service\Swess, not in
        // phpFox's generic :setting mechanism.
    }

    protected function setUserGroupSettings()
    {
    }

    protected function setComponent()
    {
        // Deliberately empty - every controller SWESS needs is already
        // registered by the main Hulahoot app's start.php. Registering
        // them again here under a second module_id would either
        // silently no-op or create a confusing duplicate dispatch path
        // for the exact same classes.
    }

    protected function setComponentBlock()
    {
    }

    protected function setOthers()
    {
        $this->_publisher = 'HolyProf';
        $this->_publisher_url = 'https://www.hulahoot.com/';
        $this->_apps_dir = 'hulahoot-swess';

        // Same admincp_route/admincp_menu mechanism as the main Hulahoot
        // app (see that Install.php's own comment on this) - resolves to
        // routes already registered in PF.Site/Apps/hulahoot/start.php's
        // admincp/hulahoot/swess/* group. Reuses the exact phrase keys
        // already defined (and already synced to :language_phrase) by
        // that app's phrase.json - no new phrase.json needed here.
        $this->admincp_route = 'admincp.hulahoot.swess-whitelist';
        $this->admincp_menu = [
            _p('hulahoot_admin_swess_whitelist') => 'hulahoot.swess-whitelist',
            _p('hulahoot_admin_swess_tags') => 'hulahoot.swess-tag',
            _p('hulahoot_admin_swess_audit') => 'hulahoot.swess-audit',
        ];
    }
}
