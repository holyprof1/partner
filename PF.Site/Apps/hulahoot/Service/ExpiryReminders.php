<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class ExpiryReminders
 *
 * Sends renewal-nudge emails around a purchase's expiry, in two phases:
 *
 * - PRE-expiry: starting getPreExpiryReminderStartDays() days BEFORE
 *   expiry_date, up to getPreExpiryReminderCount() emails, spread evenly
 *   across that window - the confirmed requirement that "the renewal/
 *   expiry process should begin before the 365-day expiry ... currently
 *   expected around 30 days before expiry".
 * - POST-expiry (grace period): the original behavior, unchanged in
 *   shape - up to getPostExpiryReminderCount() emails spread evenly
 *   across Marketplace::getGracePeriodDays() days AFTER expiry_date,
 *   requested directly: "during that grace period.. mails go dey send
 *   maybe 5 times".
 *
 * Every count/day setting above is admin-configurable (AdminCP ->
 * Settings -> Hulahoot Profiles, see Install.php's setSettings()) rather
 * than hardcoded - the confirmed requirement that "Admin must be able to
 * edit the relevant duration/reminder/expiry settings". The interval
 * between reminders within either phase is not a separate setting - it's
 * derived (window days / count) so reminders always stay evenly spaced
 * regardless of what an admin configures, matching this class's original
 * design intent exactly (30 days / 5 reminders = 6 days apart, the exact
 * figure this class shipped with before settings existed).
 *
 * Intended to run once a day via cron.php/send-expiry-reminders.php.
 *
 * hulahoot_expiry_reminder tracks how many reminders of EACH phase a
 * purchase has already had, independently (reminder_count/last_sent for
 * post-expiry, pre_expiry_reminder_count/pre_expiry_last_sent for
 * pre-expiry) - no row means zero of either kind sent. Once a phase's
 * count reaches its cap, or the purchase leaves that phase's window
 * entirely (renewed, or - for post-expiry - the grace window fully
 * lapsed and the slot returned to the market, see Marketplace::
 * getOccupiedSlotCount()), it's simply skipped from then on; nothing
 * else needs to "turn the reminders off".
 *
 * @package Apps\Hulahoot\Service
 */
class ExpiryReminders
{
    /** Documented DEFAULT - see getPostExpiryReminderCount(). */
    const MAX_REMINDERS = 5;

    /** Documented DEFAULT - see getPreExpiryReminderCount(). */
    const DEFAULT_PRE_EXPIRY_REMINDER_COUNT = 3;

    /** Documented DEFAULT - see getPreExpiryReminderStartDays(). */
    const DEFAULT_PRE_EXPIRY_START_DAYS = 30;

    /**
     * @return int currently admin-configured count of post-expiry
     *         (grace period) reminders
     */
    public static function getPostExpiryReminderCount()
    {
        $iValue = (int)Phpfox::getParam('hulahoot.post_expiry_reminder_count');

        return $iValue > 0 ? $iValue : self::MAX_REMINDERS;
    }

    /**
     * @return int currently admin-configured count of pre-expiry reminders
     */
    public static function getPreExpiryReminderCount()
    {
        $iValue = (int)Phpfox::getParam('hulahoot.pre_expiry_reminder_count');

        return $iValue > 0 ? $iValue : self::DEFAULT_PRE_EXPIRY_REMINDER_COUNT;
    }

    /**
     * @return int currently admin-configured number of days before
     *         expiry that the first pre-expiry reminder goes out
     */
    public static function getPreExpiryReminderStartDays()
    {
        $iValue = (int)Phpfox::getParam('hulahoot.pre_expiry_reminder_start_days');

        return $iValue > 0 ? $iValue : self::DEFAULT_PRE_EXPIRY_START_DAYS;
    }

    /**
     * Runs both reminder phases under a single named lock for the whole
     * run - without it, two overlapping runs (e.g. a slow run still
     * finishing when the next cron tick fires) would both read the same
     * "not yet reminded today" state for a purchase and both send -
     * confirmed live by launching 5 concurrent runs against one due
     * purchase, which sent 5 duplicate emails before 4 of them crashed on
     * the resulting primary-key collision. A single lock around the
     * whole method (rather than one per purchase, or one per phase) is
     * enough - this job is small and runs once a day, so serializing the
     * entire run costs nothing real.
     *
     * Safe to call more than once on the same day - a purchase already
     * reminded (in either phase) today is skipped.
     *
     * @return int how many reminder emails were actually sent, across
     *         both phases
     */
    public function sendDueReminders()
    {
        $aLockResult = db()->select("GET_LOCK('hulahoot_expiry_reminders_cron', 30) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            // Another run is already in progress - let it finish rather
            // than duplicate its work; the next scheduled tick will pick
            // up anything still due.
            return 0;
        }

        try {
            return $this->sendDuePreExpiryReminders() + $this->sendDuePostExpiryReminders();
        } finally {
            db()->select("RELEASE_LOCK('hulahoot_expiry_reminders_cron') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * Every completed, Hulahoot-managed purchase whose expiry_date falls
     * within the pre-expiry reminder window (now .. now + start-days)
     * and hasn't expired yet, sends whichever of its capped pre-expiry
     * reminders is next due, spread evenly across that window.
     *
     * A purchase still sitting at expiry_date = 0 (not yet reconciled to
     * its real term - see Marketplace::reconcilePurchaseTermsForUser())
     * never matches here (0 is not > now), which is correct: nothing to
     * remind about until its real expiry is known.
     *
     * @return int how many pre-expiry reminder emails were sent
     */
    private function sendDuePreExpiryReminders()
    {
        $iNow = time();
        $iStartDays = self::getPreExpiryReminderStartDays();
        $iCount = self::getPreExpiryReminderCount();

        if ($iCount < 1 || $iStartDays < 1) {
            // An admin can effectively disable this phase by setting
            // either to 0.
            return 0;
        }

        $iWindowEnd = $iNow + ($iStartDays * 86400);
        $iIntervalDays = max(1, (int)floor($iStartDays / $iCount));

        $aDue = db()->select('sp.purchase_id, sp.user_id, sp.package_id, sp.expiry_date')
            ->from(':subscribe_purchase', 'sp')
            ->join(':hulahoot_subscription_package', 'hsp', 'hsp.package_id = sp.package_id')
            ->where('sp.status = "completed" AND sp.expiry_date > ' . $iNow . ' AND sp.expiry_date <= ' . $iWindowEnd)
            ->execute('getSlaveRows');

        if (!$aDue) {
            return 0;
        }

        return $this->sendBatch($aDue, 'pre_expiry_reminder_count', 'pre_expiry_last_sent', $iCount, function ($aPurchase) use ($iNow, $iIntervalDays) {
            // Days already INTO the pre-expiry window (not days until
            // expiry) - mirrors sendDuePostExpiryReminders()'s own
            // "days into grace" cadence math exactly, just anchored to
            // the window's start instead of expiry_date.
            $iWindowStart = (int)$aPurchase['expiry_date'] - (self::getPreExpiryReminderStartDays() * 86400);

            return (int)floor(($iNow - $iWindowStart) / 86400 / $iIntervalDays);
        }, 'pre_expiry');
    }

    /**
     * Every completed, Hulahoot-managed purchase whose term has ended
     * but is still inside its grace window - exactly the same "occupied
     * but lapsed" set Marketplace::getOccupiedSlotCount() still counts
     * as taken - sends whichever of its capped post-expiry reminders is
     * next due, spread evenly across the grace window.
     *
     * @return int how many post-expiry reminder emails were sent
     */
    private function sendDuePostExpiryReminders()
    {
        $iNow = time();
        $iGraceDays = Marketplace::getGracePeriodDays();
        $iCount = self::getPostExpiryReminderCount();

        if ($iCount < 1 || $iGraceDays < 1) {
            return 0;
        }

        $iGraceCutoff = $iNow - ($iGraceDays * 86400);
        $iIntervalDays = max(1, (int)floor($iGraceDays / $iCount));

        $aDue = db()->select('sp.purchase_id, sp.user_id, sp.package_id, sp.expiry_date')
            ->from(':subscribe_purchase', 'sp')
            ->join(':hulahoot_subscription_package', 'hsp', 'hsp.package_id = sp.package_id')
            ->where('sp.status = "completed" AND sp.expiry_date > 0 AND sp.expiry_date <= ' . $iNow . ' AND sp.expiry_date > ' . $iGraceCutoff)
            ->execute('getSlaveRows');

        if (!$aDue) {
            return 0;
        }

        return $this->sendBatch($aDue, 'reminder_count', 'last_sent', $iCount, function ($aPurchase) use ($iNow, $iIntervalDays) {
            $iDaysIntoGrace = (int)floor(($iNow - (int)$aPurchase['expiry_date']) / 86400);

            return (int)floor($iDaysIntoGrace / $iIntervalDays);
        }, 'post_expiry');
    }

    /**
     * Shared send/cadence/tracking logic for both phases - identical
     * shape, differing only in which hulahoot_expiry_reminder columns
     * they track and which email copy they send.
     *
     * @param array $aDuePurchases {purchase_id, user_id, package_id, expiry_date}[]
     * @param string $sCountColumn reminder_count or pre_expiry_reminder_count
     * @param string $sLastSentColumn last_sent or pre_expiry_last_sent
     * @param int $iCap this phase's admin-configured max reminder count
     * @param callable $fnDueAtInterval (array $aPurchase): int - returns
     *        how many interval-lengths have elapsed for this purchase
     *        right now; a reminder is due once this reaches the number
     *        already sent
     * @param string $sPhase 'pre_expiry' | 'post_expiry' - which email
     *        copy sendReminderEmail() should use
     *
     * @return int how many emails were actually sent this call
     */
    private function sendBatch(array $aDuePurchases, $sCountColumn, $sLastSentColumn, $iCap, callable $fnDueAtInterval, $sPhase)
    {
        $iNow = time();
        $iOneDayAgo = $iNow - 86400;

        $aReminderRows = db()->select('*')
            ->from(':hulahoot_expiry_reminder')
            ->where('purchase_id IN (' . implode(',', array_column($aDuePurchases, 'purchase_id')) . ')')
            ->execute('getSlaveRows');
        $aReminderByPurchaseId = [];
        foreach ($aReminderRows as $aReminder) {
            $aReminderByPurchaseId[(int)$aReminder['purchase_id']] = $aReminder;
        }

        $iSentCount = 0;

        foreach ($aDuePurchases as $aPurchase) {
            $iPurchaseId = (int)$aPurchase['purchase_id'];
            $aReminder = $aReminderByPurchaseId[$iPurchaseId] ?? [$sCountColumn => 0, $sLastSentColumn => 0];
            $iSentSoFar = (int)$aReminder[$sCountColumn];

            if ($iSentSoFar >= $iCap) {
                continue;
            }

            if ((int)$aReminder[$sLastSentColumn] > $iOneDayAgo) {
                continue;
            }

            if ($fnDueAtInterval($aPurchase) < $iSentSoFar) {
                continue;
            }

            // One buyer's flaky mailbox/SMTP hiccup must not stop the
            // rest of this batch from getting their own due reminder.
            try {
                $this->sendReminderEmail($aPurchase, $sPhase);
            } catch (\Throwable $e) {
                continue;
            }

            if (isset($aReminderByPurchaseId[$iPurchaseId])) {
                db()->update(':hulahoot_expiry_reminder', [
                    $sCountColumn => $iSentSoFar + 1,
                    $sLastSentColumn => $iNow,
                ], 'purchase_id = ' . $iPurchaseId);
            } else {
                db()->insert(':hulahoot_expiry_reminder', [
                    'purchase_id' => $iPurchaseId,
                    $sCountColumn => 1,
                    $sLastSentColumn => $iNow,
                ]);
            }

            $iSentCount++;
        }

        return $iSentCount;
    }

    /**
     * @param array $aPurchase {purchase_id, user_id, package_id, expiry_date}
     * @param string $sPhase 'pre_expiry' | 'post_expiry'
     */
    private function sendReminderEmail(array $aPurchase, $sPhase)
    {
        $sLink = \Phpfox_Url::instance()->makeUrl('subscribe.view', ['id' => (int)$aPurchase['purchase_id']]);

        $sSubjectVar = $sPhase === 'pre_expiry' ? 'hulahoot_pre_expiry_reminder_subject' : 'hulahoot_expiry_reminder_subject';
        $sMessageVar = $sPhase === 'pre_expiry' ? 'hulahoot_pre_expiry_reminder_message' : 'hulahoot_expiry_reminder_message';

        $aMessageParams = [
            'site_title' => Phpfox::getParam('core.site_title'),
            'link' => $sLink,
        ];

        if ($sPhase === 'pre_expiry') {
            $aMessageParams['days'] = max(0, (int)floor(((int)$aPurchase['expiry_date'] - time()) / 86400));
        }

        Phpfox::getLib('mail')
            ->to((int)$aPurchase['user_id'])
            ->subject([$sSubjectVar, ['site_title' => Phpfox::getParam('core.site_title')]])
            ->message([$sMessageVar, $aMessageParams])
            ->send();
    }
}
