<?php

namespace Apps\Hulahoot\Service;

/**
 * Class Campaign
 *
 * Milestone 2: the composer's "campaign association" requirement. A
 * campaign is a publisher-owned grouping of SWESS posts - see
 * Installation/Database/SwessCampaign.php's own docblock for why this is
 * per-user rather than shared/global.
 *
 * create() is the one place hulahoot_subscription_package.campaign_limit
 * (a real column since Milestone 1, previously with nothing counting
 * against it - Entitlement::getActiveEntitlement()'s own campaigns_used
 * was hardcoded to 0) is finally enforced.
 *
 * @package Apps\Hulahoot\Service
 */
class Campaign
{
    /**
     * @param int $iUserId
     * @param string $sName
     * @param string|null $sDescription
     *
     * @return int the new campaign_id
     *
     * @throws \InvalidArgumentException if the name is blank, or the
     *         user's own campaign_limit (Entitlement::getActiveEntitlement(),
     *         null = unlimited) is already reached by their active
     *         campaigns
     */
    public function create($iUserId, $sName, $sDescription = null)
    {
        $iUserId = (int)$iUserId;
        $sName = trim((string)$sName);

        if ($sName === '') {
            throw new \InvalidArgumentException(_p('hulahoot_swess_campaign_name_required'));
        }

        $aEntitlement = (new Entitlement())->getActiveEntitlement($iUserId);
        $iLimit = $aEntitlement['campaign_limit'] ?? null;

        if ($iLimit !== null) {
            $iActiveCount = (int)db()->select('COUNT(*)')
                ->from(':hulahoot_swess_campaign')
                ->where(['user_id' => $iUserId, 'is_active' => 1])
                ->execute('getSlaveField');

            if ($iActiveCount >= (int)$iLimit) {
                throw new \InvalidArgumentException(_p('hulahoot_swess_campaign_limit_reached', ['limit' => (int)$iLimit]));
            }
        }

        $iNow = time();

        return (int)db()->insert(':hulahoot_swess_campaign', [
            'user_id' => $iUserId,
            'name' => mb_substr($sName, 0, 150),
            'description' => $sDescription !== null && trim((string)$sDescription) !== '' ? trim((string)$sDescription) : null,
            'is_active' => 1,
            'created' => $iNow,
            'updated' => $iNow,
        ]);
    }

    /**
     * @param int $iUserId
     *
     * @return array active campaigns only, newest first
     */
    public function getActiveByUserId($iUserId)
    {
        return (array)db()->select('*')
            ->from(':hulahoot_swess_campaign')
            ->where(['user_id' => (int)$iUserId, 'is_active' => 1])
            ->order('campaign_id DESC')
            ->execute('getSlaveRows');
    }

    /**
     * @param int $iCampaignId
     *
     * @return array|false
     */
    public function getById($iCampaignId)
    {
        return db()->select('*')
            ->from(':hulahoot_swess_campaign')
            ->where(['campaign_id' => (int)$iCampaignId])
            ->execute('getSlaveRow');
    }

    /**
     * @param int $iCampaignId
     * @param int $iUserId
     *
     * @return bool true if $iCampaignId exists, is active, and belongs to $iUserId
     */
    public function belongsToUser($iCampaignId, $iUserId)
    {
        $aCampaign = $this->getById($iCampaignId);

        return $aCampaign && (int)$aCampaign['user_id'] === (int)$iUserId && (int)$aCampaign['is_active'] === 1;
    }
}
