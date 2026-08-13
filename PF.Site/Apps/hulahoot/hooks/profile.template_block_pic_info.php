<?php
/**
 * Renders into the profile header at an official, previously-unused
 * phpFox extension point - {plugin call='profile.template_block_pic_info'}
 * in PF.Base/module/profile/template/default/block/pic.html.php, right
 * under the profile owner's name/bio and above the tab menu. Compiles to
 * Phpfox_Plugin::get(...) + eval(), the same mechanism this App's other
 * hooks/ files already use (see hooks/user.template_default_block_
 * register_step1_4.php) - no core file touched to add this button.
 *
 * Links to the SWESS dashboard (/hulahoot/swess) - the dashboard itself
 * gates on Service\Swess (whitelist), not this button; an unauthorized
 * user can still click through and will see a clear "not authorized"
 * state there rather than a dead link. This hook only needs to change
 * again if the entry point itself moves.
 *
 * Only rendered on the profile owner's own page (this block also renders
 * when viewing someone else's profile, where an action button to create
 * a promotion on someone else's behalf would be wrong). Uses
 * PHPFOX_CURRENT_TIMELINE_PROFILE (defined in
 * Profile_Component_Controller_Index::process() before this template
 * renders) rather than the compiled template's $this->_aVars['aUser'] -
 * a global constant is stable across template-compiler internals, where
 * the exact variable-storage shape isn't part of any documented contract
 * (confirmed by reading the compiled cache output directly: this file's
 * own $aUser bare-variable assumption was wrong the first time round -
 * the compiled code reads $this->_aVars['aUser'], not a local $aUser).
 *
 * Deliberately plain echo/string-concat, not PHP tag-switching - see the
 * registration hook's docblock for why (confirmed unreliable in this
 * eval() context on this server).
 */
if (defined('PHPFOX_CURRENT_TIMELINE_PROFILE') && (int)PHPFOX_CURRENT_TIMELINE_PROFILE === (int)Phpfox::getUserId()) {
    echo '<div class="hulahoot-create-promotion">';
    echo '<a href="/hulahoot/swess" class="btn btn-primary btn-sm">';
    echo htmlspecialchars(_p('hulahoot_swess_dashboard'));
    echo '</a>';
    echo '</div>';
}
