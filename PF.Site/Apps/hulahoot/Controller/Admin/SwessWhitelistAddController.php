<?php

namespace Apps\Hulahoot\Controller\Admin;

use Phpfox;
use Phpfox_Component;
use Phpfox_Plugin;

defined('PHPFOX') or exit('NO DICE!');

/**
 * One user's SWESS configuration - whitelist flags, approved identities
 * (self + specific Pages), and each approved identity's allowed
 * disclosure tags, all on one screen. Several small POST actions land
 * here rather than each getting its own controller (discriminated by
 * the 'do' field) - the same "one focused sub-page" shape as
 * IndustryPackagesController, just with more than one kind of edit
 * happening on it.
 */
class SwessWhitelistAddController extends Phpfox_Component
{
    public function process()
    {
        \Apps\Hulahoot\Service\AdmincpChrome::apply($this->template(), \Apps\Hulahoot\Service\AdmincpChrome::swessLinks());

        $service = new \Apps\Hulahoot\Service\Swess();
        $req = $this->request();
        $error = null;
        $iActorUserId = Phpfox::getUserId();

        $iWhitelistId = (int)$req->get('id');
        $aWhitelist = $iWhitelistId ? $service->getWhitelistById($iWhitelistId) : null;

        if ($iWhitelistId && !$aWhitelist) {
            $this->url()->send('/admincp/hulahoot/swess/whitelist', [], _p('hulahoot_swess_entry_not_found'));
        }

        // A blank new-entry form still needs is_enabled/post_as_self/
        // post_as_business keys to exist for the template's {if
        // $whitelist.X} checks - same "form ?: [defaults]" shape every
        // other add/edit controller in this app already uses.
        $aWhitelistForTemplate = $aWhitelist ?: ['is_enabled' => 0, 'post_as_self' => 0, 'post_as_business' => 0];

        $aOwner = $aWhitelist
            ? db()->select('user_id, user_name, full_name')->from(':user')->where(['user_id' => (int)$aWhitelist['user_id']])->execute('getSlaveRow')
            : null;

        if ($req->method() === 'POST') {
            if ($req->get('hulahoot_token') !== Phpfox::getService('log.session')->getToken()) {
                $error = _p('hulahoot_invalid_token');
            } else {
                $sDo = (string)$req->get('do');

                try {
                    switch ($sDo) {
                        case 'save_whitelist':
                            $iUserId = (int)($aOwner['user_id'] ?? 0);

                            if (!$iUserId) {
                                $aFound = $service->findUserByUsernameOrEmail((string)$req->get('user_lookup'));
                                if (!$aFound) {
                                    throw new \InvalidArgumentException(_p('hulahoot_swess_user_not_found'));
                                }
                                $iUserId = (int)$aFound['user_id'];
                            }

                            $iSavedId = $service->setWhitelist($iUserId, [
                                'is_enabled' => $req->get('is_enabled') ? 1 : 0,
                                'post_as_self' => $req->get('post_as_self') ? 1 : 0,
                                'post_as_business' => $req->get('post_as_business') ? 1 : 0,
                            ], $iActorUserId);

                            $this->url()->send('/admincp/hulahoot/swess/whitelist/add', ['id' => $iSavedId], _p('hulahoot_swess_whitelist_saved'));
                            break;

                        case 'approve_identity':
                            $service->approveIdentity(
                                $iWhitelistId,
                                (int)$aWhitelist['user_id'],
                                (string)$req->get('identity_type'),
                                (int)$req->get('identity_id'),
                                $iActorUserId
                            );

                            $this->url()->send('/admincp/hulahoot/swess/whitelist/add', ['id' => $iWhitelistId], _p('hulahoot_swess_identity_approved'));
                            break;

                        case 'revoke_identity':
                            $service->revokeIdentity((int)$req->get('approved_identity_id'), $iActorUserId);

                            $this->url()->send('/admincp/hulahoot/swess/whitelist/add', ['id' => $iWhitelistId], _p('hulahoot_swess_identity_revoked'));
                            break;

                        case 'assign_tag':
                            $service->assignTag(
                                (int)$req->get('approved_identity_id'),
                                (int)$req->get('tag_id'),
                                (bool)$req->get('is_default')
                            );

                            $this->url()->send('/admincp/hulahoot/swess/whitelist/add', ['id' => $iWhitelistId], _p('hulahoot_swess_tag_assigned'));
                            break;

                        case 'unassign_tag':
                            $service->unassignTag((int)$req->get('approved_identity_id'), (int)$req->get('tag_id'));

                            $this->url()->send('/admincp/hulahoot/swess/whitelist/add', ['id' => $iWhitelistId], _p('hulahoot_swess_tag_removed'));
                            break;
                    }
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $aIdentities = $iWhitelistId ? $service->getApprovedIdentities($iWhitelistId) : [];

        $this->template()->setTitle($aOwner ? _p('hulahoot_edit_swess_whitelist') : _p('hulahoot_add_swess_whitelist'))
            ->setBreadCrumb($aOwner ? _p('hulahoot_edit_swess_whitelist') : _p('hulahoot_add_swess_whitelist'))
            ->assign([
                'whitelist_id' => $iWhitelistId,
                'whitelist' => $aWhitelistForTemplate,
                'owner' => $aOwner,
                'identities' => $aIdentities,
                'tags' => (new \Apps\Hulahoot\Service\Swess())->listActiveTags(),
                'error' => $error,
                'csrf_token' => Phpfox::getService('log.session')->getToken(),
            ]);
    }

    public function clean()
    {
        (($sPlugin = Phpfox_Plugin::get('hulahoot.component_controller_admincp_swess_whitelist_add_clean')) ? eval($sPlugin) : false);
    }
}
