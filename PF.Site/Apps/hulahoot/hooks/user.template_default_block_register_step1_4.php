<?php
/**
 * Renders into the registration form at an official, previously-unused
 * phpFox extension point - {plugin call='user.template_default_block_
 * register_step1_4'} in PF.Base/module/user/template/default/block/
 * register/step1.html.php (and the equivalent hula2/material flavor
 * templates), right after the password field. Compiles to
 * Phpfox_Plugin::get(...) + eval(), the same mechanism this App's own
 * hooks/ directory already uses elsewhere - no core file was touched to
 * add this field.
 *
 * Submits as val[hulahoot_profile_type_id], which flows through
 * User_Component_Controller_Register unchanged (it passes the whole
 * $aVals array into user.process's add()) and is read back out in
 * hooks/user.service_process_add_end.php.
 *
 * Deliberately uses plain echo/string-concat instead of PHP tag-switching
 * (<?php foreach(...): ?>...<?php endforeach; ?>) - on partnershipportal,
 * tag-switching inside this eval()'d hook body intermittently produced
 * zero output for the whole loop (confirmed via direct instrumentation:
 * the query and $aHulahootTypes array were correct every time, but the
 * <option> tags never reached the response). Plain echo() was reliable
 * across many repeated live requests; tag-switching was not. Root cause
 * is environment-specific eval() handling of embedded '?>'/'<?php'
 * transitions, not this App's query/service logic.
 *
 * The individual-type option (e.g. "Personal") is deliberately never
 * marked selected="selected" here, even though hulahoot_profile_type's
 * own is_default flag is set on one of them - that flag means "which type
 * user.service_process_add_end.php falls back to if the field is
 * missing/tampered," not "which option the <select> should show on page
 * load." The field's existing `required` attribute forces an explicit
 * choice before submit.
 */
$oHulahootProfile = new \Apps\Hulahoot\Service\Profile();
$aHulahootTypes = $oHulahootProfile->getActiveIndividualProfileTypes();

echo '<div class="form-group">';
echo '<select class="form-control" name="val[hulahoot_profile_type_id]" id="hulahoot_profile_type_id" required>';
echo '<option value="" selected="selected">' . htmlspecialchars(_p('hulahoot_select_account_type')) . '</option>';
foreach ($aHulahootTypes as $aHulahootType) {
    echo '<option value="' . (int)$aHulahootType['profile_type_id'] . '">' . htmlspecialchars(_p($aHulahootType['name'])) . '</option>';
}
echo '</select>';
echo '</div>';
