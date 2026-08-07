<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class ExpiryReminders
 *
 * Sends up to MAX_REMINDERS renewal-nudge emails to a buyer during their
 * purchase's post-expiry grace window (Marketplace::GRACE_PERIOD_DAYS) -
 * requested directly: "during that grace period.. mails go dey send
 * maybe 5 times". Spread evenly across the grace window (one roughly
 * every REMINDER_INTERVAL_DAYS days) rather than firing all 5 back to
 * back - intended to be run once a day via cron.php/send-expiry-reminders.php.
 *
 * hulahoot_expiry_reminder tracks how many reminders a purchase has
 * already had - no row means zero sent. Once reminder_count reaches
 * MAX_REMINDERS, or the purchase leaves its grace window entirely (either
 * renewed, or the window fully lapsed and the slot returned to the
 * market - see Marketplace::getOccupiedSlotCount()), it's simply skipped
 * from then on; nothing else needs to "turn the reminders off".
 *
 * @package Apps\Hulahoot\Service
 */
class ExpiryReminders
{
    const MAX_REMINDERS = 5;

    /**
     * self::MAX_REMINDERS reminders spread across
     * Marketplace::GRACE_PERIOD_DAYS, evenly spaced.
     */
    const REMINDER_INTERVAL_DAYS = 6;

    /**
     * Finds every completed purchase currently in its grace window that's
     * due for its next reminder, sends it, and records it. Safe to call
     * more than once on the same day - a purchase already reminded today
     * is skipped.
     *
     * Wrapped in a named lock for the whole run: without it, two
     * overlapping runs (e.g. a slow run still finishing when the next
     * cron tick fires) would both read the same "not yet reminded today"
     * state for a purchase and both send - confirmed live by launching 5
     * concurrent runs against one due purchase, which sent 5 duplicate
     * emails before 4 of them crashed on the resulting primary-key
     * collision. A single lock around the whole method (rather than one
     * per purchase) is enough - this job is small and runs once a day,
     * so serializing the entire run costs nothing real, and it's simpler
     * than per-row locking for no practical benefit here.
     *
     * @return int how many reminder emails were actually sent
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
            $iNow = time();
            $iGraceCutoff = $iNow - (Marketplace::GRACE_PERIOD_DAYS * 86400);

            // Every completed purchase whose term has ended but is still
            // inside its grace window - exactly the same "occupied but
            // lapsed" set Marketplace::getOccupiedSlotCount() still counts
            // as taken.
            $aExpiredInGrace = db()->select('purchase_id, user_id, package_id, expiry_date')
                ->from(':subscribe_purchase')
                ->where('status = "completed" AND expiry_date > 0 AND expiry_date <= ' . $iNow . ' AND expiry_date > ' . $iGraceCutoff)
                ->execute('getSlaveRows');

            if (!$aExpiredInGrace) {
                return 0;
            }

            $aReminderRows = db()->select('*')
                ->from(':hulahoot_expiry_reminder')
                ->where('purchase_id IN (' . implode(',', array_column($aExpiredInGrace, 'purchase_id')) . ')')
                ->execute('getSlaveRows');
            $aReminderByPurchaseId = [];
            foreach ($aReminderRows as $aReminder) {
                $aReminderByPurchaseId[(int)$aReminder['purchase_id']] = $aReminder;
            }

            $iSentCount = 0;
            $iOneDayAgo = $iNow - 86400;

            foreach ($aExpiredInGrace as $aPurchase) {
                $iPurchaseId = (int)$aPurchase['purchase_id'];
                $aReminder = $aReminderByPurchaseId[$iPurchaseId] ?? ['reminder_count' => 0, 'last_sent' => 0];

                if ((int)$aReminder['reminder_count'] >= self::MAX_REMINDERS) {
                    continue;
                }

                if ((int)$aReminder['last_sent'] > $iOneDayAgo) {
                    continue;
                }

                $iDaysIntoGrace = (int)floor(($iNow - (int)$aPurchase['expiry_date']) / 86400);
                $iDueAtDay = (int)$aReminder['reminder_count'] * self::REMINDER_INTERVAL_DAYS;

                if ($iDaysIntoGrace < $iDueAtDay) {
                    continue;
                }

                $this->sendReminderEmail($aPurchase);

                if (isset($aReminderByPurchaseId[$iPurchaseId])) {
                    db()->update(':hulahoot_expiry_reminder', [
                        'reminder_count' => (int)$aReminder['reminder_count'] + 1,
                        'last_sent' => $iNow,
                    ], 'purchase_id = ' . $iPurchaseId);
                } else {
                    db()->insert(':hulahoot_expiry_reminder', [
                        'purchase_id' => $iPurchaseId,
                        'reminder_count' => 1,
                        'last_sent' => $iNow,
                    ]);
                }

                $iSentCount++;
            }

            return $iSentCount;
        } finally {
            db()->select("RELEASE_LOCK('hulahoot_expiry_reminders_cron') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * @param array $aPurchase {purchase_id, user_id, package_id, expiry_date}
     */
    private function sendReminderEmail(array $aPurchase)
    {
        $sLink = \Phpfox_Url::instance()->makeUrl('subscribe.view', ['id' => (int)$aPurchase['purchase_id']]);

        Phpfox::getLib('mail')
            ->to((int)$aPurchase['user_id'])
            ->subject(['hulahoot_expiry_reminder_subject', ['site_title' => Phpfox::getParam('core.site_title')]])
            ->message(['hulahoot_expiry_reminder_message', [
                'site_title' => Phpfox::getParam('core.site_title'),
                'link' => $sLink,
            ]])
            ->send();
    }
}
