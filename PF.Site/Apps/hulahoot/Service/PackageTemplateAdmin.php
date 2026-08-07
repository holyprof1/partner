<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class PackageTemplateAdmin
 *
 * AdminCP-only CRUD for hulahoot_package_template - the "Default
 * Packages" library (Lite/Elite/Dominance to start) an admin manages
 * once and reuses across every Industry, plus createPackageFromTemplate(),
 * which is what an Industry's "Create from Template" picker actually
 * calls: it creates a brand new, fully independent native package (own
 * price/name/features, own single Industry link) seeded from the
 * template's values - editing the resulting package never touches the
 * template, and editing the template never touches any package already
 * created from it, matching the explicit "each Industry's plan is its
 * own, editable without side effects elsewhere" decision (see
 * split-packages-per-industry.php).
 *
 * Every public method here is meant to be called only from an
 * admincp/hulahoot/* route, after the caller has already checked
 * admincp.has_admin_access - matches every other *Admin service in this
 * app.
 *
 * @package Apps\Hulahoot\Service
 */
class PackageTemplateAdmin
{
    /**
     * @return array every template, active or not, in display order
     */
    public function listAll()
    {
        return (array)db()->select('*')
            ->from(':hulahoot_package_template')
            ->order('ordering ASC, template_id ASC')
            ->execute('getSlaveRows');
    }

    /**
     * @return array only active templates - what the "Create from
     *         Template" picker on an Industry's Manage Packages screen
     *         offers.
     */
    public function listActive()
    {
        return (array)db()->select('*')
            ->from(':hulahoot_package_template')
            ->where(['is_active' => 1])
            ->order('ordering ASC, template_id ASC')
            ->execute('getSlaveRows');
    }

    /**
     * @param int $iTemplateId
     *
     * @return array|false
     */
    public function getById($iTemplateId)
    {
        return db()->select('*')
            ->from(':hulahoot_package_template')
            ->where(['template_id' => (int)$iTemplateId])
            ->execute('getSlaveRow');
    }

    /**
     * @param array $aData see _validate()
     *
     * @return int the new template_id
     *
     * @throws \InvalidArgumentException on any validation failure
     */
    public function create(array $aData)
    {
        $aClean = $this->_validate($aData);

        if (!isset($aData['ordering']) || $aData['ordering'] === '') {
            $aClean['ordering'] = 1 + (int)db()->select('MAX(ordering)')
                ->from(':hulahoot_package_template')
                ->execute('getSlaveField');
        }

        return (int)db()->insert(':hulahoot_package_template', array_merge($aClean, [
            'created' => time(),
            'updated' => time(),
        ]));
    }

    /**
     * @param int $iTemplateId
     * @param array $aData same fields as create()
     *
     * @return bool
     *
     * @throws \InvalidArgumentException on any validation failure
     */
    public function update($iTemplateId, array $aData)
    {
        $iTemplateId = (int)$iTemplateId;

        if (!$this->getById($iTemplateId)) {
            throw new \InvalidArgumentException('Template ' . $iTemplateId . ' does not exist.');
        }

        $aClean = $this->_validate($aData);
        $aClean['updated'] = time();

        db()->update(':hulahoot_package_template', $aClean, ['template_id' => $iTemplateId]);

        return true;
    }

    /**
     * Deleting a template never touches any package already created from
     * it - there is no reference from a package back to the template it
     * came from, by design (a clone is fully independent from the
     * moment it's created).
     *
     * @param int $iTemplateId
     *
     * @return bool
     *
     * @throws \InvalidArgumentException if the row doesn't exist
     */
    public function delete($iTemplateId)
    {
        $iTemplateId = (int)$iTemplateId;

        if (!$this->getById($iTemplateId)) {
            throw new \InvalidArgumentException('Template ' . $iTemplateId . ' does not exist.');
        }

        db()->delete(':hulahoot_package_template', ['template_id' => $iTemplateId]);

        return true;
    }

    /**
     * Creates one brand new, fully independent native package for
     * $iIndustryId, seeded from $iTemplateId - the "Create from
     * Template" action on an Industry's Manage Packages screen. Titled
     * "{Industry} - {Template name}" natively (so AdminCP's own flat
     * Subscription Packages list stays distinguishable, same convention
     * split-packages-per-industry.php established), with display_name
     * set to just the template's name so the public Industry page shows
     * the clean tier name.
     *
     * Calls Phpfox::getService('subscribe.process')->add() directly rather than
     * through the HTTP-form round-trip the CLI migration scripts use
     * (see seed-demo-data.php's docblock for why those scripts needed
     * that) - this always runs inside a real, already-authenticated
     * AdminCP request, so no workaround is needed here.
     *
     * That native add() generates its own title phrase var_name from
     * md5($languageCode . time()) - only second-precision, so two
     * packages created within the same second collide onto one phrase
     * (confirmed directly in Apps\Core_Subscriptions\Service\Process::add(),
     * and hit repeatedly by this app's own migration scripts - see their
     * git history). Guarded against here: the resulting title is always
     * verified against the intended text immediately after creation, and
     * self-repaired (a fresh, guaranteed-unique phrase) if it doesn't
     * match, rather than relying on a human to notice and fix it later.
     *
     * @param int $iTemplateId
     * @param int $iIndustryId
     *
     * @return int the new package_id
     *
     * @throws \InvalidArgumentException if the template or Industry doesn't exist
     */
    public function createPackageFromTemplate($iTemplateId, $iIndustryId)
    {
        $aTemplate = $this->getById($iTemplateId);
        if (!$aTemplate) {
            throw new \InvalidArgumentException('Template ' . (int)$iTemplateId . ' does not exist.');
        }

        $aIndustry = (new IndustryAdmin())->getById($iIndustryId);
        if (!$aIndustry) {
            throw new \InvalidArgumentException('Industry ' . (int)$iIndustryId . ' does not exist.');
        }

        $sIndustryName = _p($aIndustry['name']);
        $sNativeTitle = $sIndustryName . ' - ' . $aTemplate['name'];
        $sCost = (string)(int)$aTemplate['default_cost'];
        $bFree = ((int)$aTemplate['default_cost'] === 0);

        $iPackageId = (int)Phpfox::getService('subscribe.process')->add([
            'title' => ['en' => $sNativeTitle],
            'description' => ['en' => (string)$aTemplate['description']],
            'cost' => ['USD' => $sCost, 'EUR' => $sCost, 'GBP' => $sCost],
            'recurring_cost' => ['USD' => $sCost, 'EUR' => $sCost, 'GBP' => $sCost],
            'recurring_period' => (string)(int)$aTemplate['recurring_period'],
            'is_free' => $bFree ? '1' : '0',
            'is_active' => '1',
            'is_registration' => '0',
            'show_price' => '1',
            // See seed-demo-data.php's own docblock for why this is 2
            // (NORMAL_USER_ID) and never 0 - purchasing a business
            // package must never change the buyer's phpFox account group.
            'user_group_id' => '2',
            'fail_user_group' => '2',
            'number_day_notify_before_expiration' => '0',
            'allow_payment_methods' => ['auto' => '1', 'manual' => '2'],
            'visible_group' => ['1', '2', '3', '4', '5', '6'],
        ]);

        $this->_ensureCorrectTitle($iPackageId, $sNativeTitle);

        $aFeatures = preg_split('/\r\n|\r|\n/', (string)$aTemplate['features_text']);

        (new SubscriptionPackageAdmin())->saveRules(
            $iPackageId,
            [
                'display_name' => $aTemplate['name'],
                'subtitle' => $aTemplate['subtitle'],
                'description' => $aTemplate['description'],
                'badge_text' => $aTemplate['badge_text'],
                'accent_color' => $aTemplate['accent_color'],
                'button_text' => $aTemplate['button_text'],
                'ordering' => $aTemplate['ordering'],
                'purchase_limit' => $aTemplate['purchase_limit'],
                'campaign_limit' => $aTemplate['campaign_limit'],
                'posting_limit_per_day' => $aTemplate['posting_limit_per_day'],
                'posting_limit_per_month' => $aTemplate['posting_limit_per_month'],
                'monthly_credits' => $aTemplate['monthly_credits'],
                'is_active' => 1,
            ],
            [(int)$iIndustryId],
            $aFeatures
        );

        return $iPackageId;
    }

    /**
     * @see createPackageFromTemplate()'s own docblock for why this exists.
     *
     * @param int $iPackageId
     * @param string $sExpectedTitle
     */
    private function _ensureCorrectTitle($iPackageId, $sExpectedTitle)
    {
        $sVarName = db()->select('title')->from(':subscribe_package')->where(['package_id' => (int)$iPackageId])->execute('getSlaveField');
        $sCurrentText = db()->select('text')->from(':language_phrase')->where(['var_name' => $sVarName])->execute('getSlaveField');

        if ($sCurrentText === $sExpectedTitle) {
            return;
        }

        $sNewVarName = 'subscription_package_title_' . md5(uniqid('', true) . mt_rand());

        db()->insert(':language_phrase', [
            'language_id' => 'en',
            'module_id' => '',
            'product_id' => 'phpfox',
            'version_id' => '',
            'var_name' => $sNewVarName,
            'text' => $sExpectedTitle,
            'text_default' => $sExpectedTitle,
            'added' => time(),
        ]);

        db()->update(':subscribe_package', ['title' => $sNewVarName], 'package_id = ' . (int)$iPackageId);
    }

    /**
     * @param array $aData
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    private function _validate(array $aData)
    {
        $fParseLimit = function ($mValue) {
            $sValue = trim((string)($mValue ?? ''));
            if ($sValue === '') {
                return null;
            }
            if (!ctype_digit($sValue)) {
                throw new \InvalidArgumentException('Limits must be a whole number, or left blank for unlimited.');
            }

            return (int)$sValue;
        };

        $sName = trim((string)($aData['name'] ?? ''));
        if ($sName === '') {
            throw new \InvalidArgumentException('Name is required.');
        }

        $sAccentColor = trim((string)($aData['accent_color'] ?? ''));
        if ($sAccentColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $sAccentColor)) {
            throw new \InvalidArgumentException('Accent color must be a hex value (e.g. #2C7BE5), or left blank.');
        }

        return [
            'name' => $sName,
            'description' => trim((string)($aData['description'] ?? '')),
            'default_cost' => max(0, (int)($aData['default_cost'] ?? 0)),
            'recurring_period' => max(0, (int)($aData['recurring_period'] ?? 1)),
            'subtitle' => trim((string)($aData['subtitle'] ?? '')),
            'badge_text' => trim((string)($aData['badge_text'] ?? '')),
            'accent_color' => $sAccentColor,
            'button_text' => trim((string)($aData['button_text'] ?? '')),
            'monthly_credits' => max(0, (int)($aData['monthly_credits'] ?? 0)),
            'purchase_limit' => $fParseLimit($aData['purchase_limit'] ?? null),
            'campaign_limit' => $fParseLimit($aData['campaign_limit'] ?? null),
            'posting_limit_per_day' => $fParseLimit($aData['posting_limit_per_day'] ?? null),
            'posting_limit_per_month' => $fParseLimit($aData['posting_limit_per_month'] ?? null),
            'features_text' => trim((string)($aData['features_text'] ?? '')),
            'ordering' => (int)($aData['ordering'] ?? 0),
            'is_active' => !empty($aData['is_active']) ? 1 : 0,
        ];
    }
}
