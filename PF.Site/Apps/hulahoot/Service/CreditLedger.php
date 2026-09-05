<?php

namespace Apps\Hulahoot\Service;

use Phpfox;

/**
 * Class CreditLedger
 *
 * Milestone 2: makes hulahoot_subscription_package.monthly_credits an
 * actual usable ledger (AVAILABLE -> RESERVED -> USED, with RELEASE back
 * to AVAILABLE) instead of the display-only number it was through
 * Milestone 1 - see Entitlement.php's own docblock, which explicitly
 * documented "no ledger, no earn/spend, Credits logic is a later phase."
 * This is that later phase.
 *
 * Deliberately keeps the ALLOCATION side exactly as it already was: the
 * number of credits a user has to work with is still a live read through
 * Entitlement::getActiveEntitlement()['monthly_credits'] (summed across
 * every active, Hulahoot-managed purchase - Entitlement's own merge rule,
 * never duplicated here). This class only tracks CONSUMPTION against that
 * live number, in hulahoot_swess_credit_account (the running reserved/used
 * totals) and hulahoot_swess_credit_transaction (the append-only history
 * of how they got there).
 *
 *   available = allocation (0 if no active package) + bonus_credits
 *               - reserved - used
 *
 * Credit-exempt users: a whitelist entry can exist with no purchased
 * package at all (an admin whitelisted the account directly - the
 * confirmed "Hulahoot staff / Admin entitlement may be credit-free"
 * case). getActiveEntitlement() returns null for such a user - this class
 * treats that as UNLIMITED credit (reserve()/hasAvailableCredit() always
 * succeed, no ledger rows are written at all), never as zero. A user who
 * DOES hold an active package but whose package grants 0 monthly_credits
 * is a different, real "no credits" case and stays correctly blocked -
 * the two are told apart by whether getActiveEntitlement() itself is
 * null, not by comparing a number to zero.
 *
 * Concurrency: the same named GET_LOCK/RELEASE_LOCK pattern already used
 * per-post (Service\Swess) and per-package (Service\PurchaseFlow), scoped
 * per-user here. Every method that mutates state acquires this lock
 * itself and is safe to call standalone - but see reserve()/release()/
 * consume()'s own docblocks: in practice they are always called from
 * inside a Service\Swess lifecycle method that already holds that post's
 * own per-post lock first, so the lock order in this codebase is always
 * post-lock-then-credit-lock, never the reverse - preserve that order if
 * extending this further, to avoid a deadlock between two requests
 * touching the same user's two different posts at once.
 *
 * @package Apps\Hulahoot\Service
 */
class CreditLedger
{
    const DEFAULT_CREDITS_PER_POST = 1;

    /**
     * @return int the currently admin-configured cost, in credits, of one
     *         SWESS post submission (AdminCP -> Settings -> Hulahoot)
     */
    public static function getCreditsPerPost()
    {
        $iValue = (int)Phpfox::getParam('hulahoot.swess_credits_per_post');

        return $iValue > 0 ? $iValue : self::DEFAULT_CREDITS_PER_POST;
    }

    /**
     * The live, package-derived credit pool - never stored, always read
     * fresh through Entitlement so it can never drift out of sync with
     * what a user actually currently holds.
     *
     * @param int $iUserId
     *
     * @return int|null null means credit-exempt (no active Hulahoot
     *         package at all - see class docblock); otherwise the summed
     *         monthly_credits across every active purchase (0 is a real,
     *         valid, enforced value here, not "unlimited").
     */
    public function getPackageAllocation($iUserId)
    {
        $aEntitlement = (new Entitlement())->getActiveEntitlement((int)$iUserId);

        if ($aEntitlement === null) {
            return null;
        }

        return (int)$aEntitlement['monthly_credits'];
    }

    /**
     * @param int $iUserId
     *
     * @return bool true if this user has no active Hulahoot package at
     *         all (an admin-only whitelist grant) - credit rules never
     *         apply to them.
     */
    public function isCreditExempt($iUserId)
    {
        return $this->getPackageAllocation($iUserId) === null;
    }

    /**
     * Reads (or lazily creates, all-zero) the running account row - same
     * "no row = nothing has happened yet, not an error" convention
     * hulahoot_swess_whitelist itself uses.
     *
     * @param int $iUserId
     *
     * @return array {user_id, bonus_credits, reserved, used, updated}
     */
    public function getOrCreateAccount($iUserId)
    {
        $iUserId = (int)$iUserId;

        $aAccount = db()->select('*')
            ->from(':hulahoot_swess_credit_account')
            ->where(['user_id' => $iUserId])
            ->execute('getSlaveRow');

        if ($aAccount) {
            return $aAccount;
        }

        $iNow = time();
        db()->insert(':hulahoot_swess_credit_account', [
            'user_id' => $iUserId,
            'bonus_credits' => 0,
            'reserved' => 0,
            'used' => 0,
            'updated' => $iNow,
        ]);

        return [
            'user_id' => $iUserId,
            'bonus_credits' => 0,
            'reserved' => 0,
            'used' => 0,
            'updated' => $iNow,
        ];
    }

    /**
     * The full picture for one user - what the SWESS composer/entitlement
     * tab and the AdminCP credit screen both render from.
     *
     * @param int $iUserId
     *
     * @return array{exempt: bool, allocation: int|null, bonus: int,
     *         reserved: int, used: int, available: int|null} available is
     *         null when exempt (unlimited); otherwise never negative.
     */
    public function getBalance($iUserId)
    {
        $iUserId = (int)$iUserId;
        $iAllocation = $this->getPackageAllocation($iUserId);
        $aAccount = $this->getOrCreateAccount($iUserId);

        if ($iAllocation === null) {
            return [
                'exempt' => true,
                'allocation' => null,
                'bonus' => (int)$aAccount['bonus_credits'],
                'reserved' => (int)$aAccount['reserved'],
                'used' => (int)$aAccount['used'],
                'available' => null,
            ];
        }

        $iAvailable = $iAllocation + (int)$aAccount['bonus_credits'] - (int)$aAccount['reserved'] - (int)$aAccount['used'];

        return [
            'exempt' => false,
            'allocation' => $iAllocation,
            'bonus' => (int)$aAccount['bonus_credits'],
            'reserved' => (int)$aAccount['reserved'],
            'used' => (int)$aAccount['used'],
            'available' => max(0, $iAvailable),
        ];
    }

    /**
     * @param int $iUserId
     * @param int $iAmount
     *
     * @return bool
     */
    public function hasAvailableCredit($iUserId, $iAmount)
    {
        $aBalance = $this->getBalance($iUserId);

        return $aBalance['exempt'] || $aBalance['available'] >= (int)$iAmount;
    }

    /**
     * Reserve $iAmount credits against $iUserId's balance for one post -
     * the AVAILABLE -> RESERVED transition. A credit-exempt user (see
     * class docblock) is a deliberate, silent no-op: nothing is written,
     * matching "credit-free access" meaning exactly that - no ledger
     * noise for an account credits were never meant to gate.
     *
     * Called from inside Service\Swess::submitPost(), which already holds
     * that post's own GET_LOCK - see class docblock on lock ordering.
     *
     * @param int $iUserId
     * @param int $iPostId
     * @param int $iAmount
     *
     * @return void
     *
     * @throws \InvalidArgumentException if insufficient credit is available
     */
    public function reserve($iUserId, $iPostId, $iAmount)
    {
        $iUserId = (int)$iUserId;
        $iAmount = (int)$iAmount;

        if ($iAmount <= 0) {
            return;
        }

        if ($this->isCreditExempt($iUserId)) {
            return;
        }

        $sLockName = 'hulahoot_swess_credit_' . $iUserId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 5) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            throw new \InvalidArgumentException(_p('hulahoot_swess_credit_busy'));
        }

        try {
            // Re-check under the lock - the balance read above (via
            // hasAvailableCredit()/getBalance() at the caller) is not
            // trustworthy once a concurrent reservation could have landed
            // between that read and acquiring this lock.
            if (!$this->hasAvailableCredit($iUserId, $iAmount)) {
                throw new \InvalidArgumentException(_p('hulahoot_swess_insufficient_credit'));
            }

            $this->getOrCreateAccount($iUserId);

            db()->update(':hulahoot_swess_credit_account', [
                'reserved' => 'reserved + ' . $iAmount,
                'updated' => time(),
            ], ['user_id' => $iUserId], false);

            $this->_logTransaction($iUserId, (int)$iPostId, 'reserve', $iAmount, null, null);
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * Return an outstanding reservation for one post back to AVAILABLE -
     * used on cancel, reject, and publish-failure. Computed from the
     * ledger itself (SUM of reserve minus SUM of already-released/consumed
     * for this post), not a fixed amount, so this is naturally idempotent:
     * calling it twice for the same post (or calling it for a post that
     * was never reserved - e.g. a credit-exempt user) finds nothing
     * outstanding the second time and does nothing.
     *
     * @param int $iUserId
     * @param int $iPostId
     * @param string|null $sReason short note stored on the ledger row -
     *        e.g. 'rejected', 'cancelled', 'publish_failed'
     *
     * @return void
     */
    public function release($iUserId, $iPostId, $sReason = null)
    {
        $this->_settle((int)$iUserId, (int)$iPostId, 'release', 'reserved', $sReason);
    }

    /**
     * Finalize an outstanding reservation as spent - the RESERVED -> USED
     * transition, at the moment SWESS's own bookkeeping considers a post
     * live (status = 'published'). Same idempotent, ledger-derived amount
     * as release() - see that method's own docblock.
     *
     * @param int $iUserId
     * @param int $iPostId
     *
     * @return void
     */
    public function consume($iUserId, $iPostId)
    {
        $this->_settle((int)$iUserId, (int)$iPostId, 'consume', 'used', null);
    }

    /**
     * Shared release()/consume() implementation - both move an
     * outstanding reservation out of `reserved`, differing only in
     * whether the amount lands back as available (release, decrements
     * `reserved` only) or as spent (consume, decrements `reserved` AND
     * increments `used`).
     *
     * @param int $iUserId
     * @param int $iPostId
     * @param string $sType 'release' | 'consume'
     * @param string $sAccountField unused for 'release' (kept for symmetry/
     *        readability at call sites); 'used' for 'consume'
     * @param string|null $sNote
     */
    private function _settle($iUserId, $iPostId, $sType, $sAccountField, $sNote)
    {
        $sLockName = 'hulahoot_swess_credit_' . $iUserId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 5) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            // A concurrent settle for the same user is already running -
            // it will finish; never block this caller waiting on it (same
            // "loser returns immediately" precedent as
            // PurchaseFlow::expandCompletedBuyout()'s own lock handling).
            return;
        }

        try {
            $iOutstanding = $this->_getOutstandingReservation($iUserId, $iPostId);

            if ($iOutstanding <= 0) {
                return;
            }

            $this->getOrCreateAccount($iUserId);

            if ($sType === 'consume') {
                db()->update(':hulahoot_swess_credit_account', [
                    'reserved' => 'GREATEST(0, reserved - ' . $iOutstanding . ')',
                    'used' => 'used + ' . $iOutstanding,
                    'updated' => time(),
                ], ['user_id' => $iUserId], false);
            } else {
                db()->update(':hulahoot_swess_credit_account', [
                    'reserved' => 'GREATEST(0, reserved - ' . $iOutstanding . ')',
                    'updated' => time(),
                ], ['user_id' => $iUserId], false);
            }

            $this->_logTransaction($iUserId, $iPostId, $sType, $iOutstanding, null, $sNote);
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * @param int $iUserId
     * @param int $iPostId
     *
     * @return int SUM(reserve) - SUM(release) - SUM(consume) for this
     *         post - always >= 0 in correct operation (never reserved
     *         twice without an intervening release/consume, per
     *         Service\Swess's own per-post locking), but clamped
     *         defensively regardless.
     */
    private function _getOutstandingReservation($iUserId, $iPostId)
    {
        $aRows = (array)db()->select('type, SUM(amount) AS total')
            ->from(':hulahoot_swess_credit_transaction')
            ->where(['user_id' => (int)$iUserId, 'swess_post_id' => (int)$iPostId])
            ->group('type')
            ->execute('getSlaveRows');

        $iReserved = 0;
        $iSettled = 0;
        foreach ($aRows as $aRow) {
            if ($aRow['type'] === 'reserve') {
                $iReserved += (int)$aRow['total'];
            } elseif ($aRow['type'] === 'release' || $aRow['type'] === 'consume') {
                $iSettled += (int)$aRow['total'];
            }
        }

        return max(0, $iReserved - $iSettled);
    }

    /**
     * Admin correction - grant ($iAmount > 0) or revoke ($iAmount < 0)
     * bonus credits on top of whatever the user's package already grants.
     * A note is mandatory: this directly changes what a paying (or
     * credit-free) user may do, per the confirmed "Correct credits" admin
     * requirement, and every such change must be traceable to a reason,
     * not just an actor.
     *
     * @param int $iUserId
     * @param int $iAmount positive to grant, negative to revoke - never 0
     * @param int $iActorUserId the admin making this change
     * @param string $sNote required, non-blank
     *
     * @return void
     *
     * @throws \InvalidArgumentException if $iAmount is 0 or $sNote is blank
     */
    public function adjustBonus($iUserId, $iAmount, $iActorUserId, $sNote)
    {
        $iUserId = (int)$iUserId;
        $iAmount = (int)$iAmount;
        $sNote = trim((string)$sNote);

        if ($iAmount === 0) {
            throw new \InvalidArgumentException(_p('hulahoot_swess_credit_adjust_amount_required'));
        }

        if ($sNote === '') {
            throw new \InvalidArgumentException(_p('hulahoot_swess_credit_adjust_note_required'));
        }

        $sLockName = 'hulahoot_swess_credit_' . $iUserId;
        $aLockResult = db()->select("GET_LOCK('" . $sLockName . "', 5) AS locked")->from('DUAL')->execute('getSlaveRow');

        if (empty($aLockResult['locked'])) {
            throw new \InvalidArgumentException(_p('hulahoot_swess_credit_busy'));
        }

        try {
            $this->getOrCreateAccount($iUserId);

            db()->update(':hulahoot_swess_credit_account', [
                'bonus_credits' => 'bonus_credits + (' . $iAmount . ')',
                'updated' => time(),
            ], ['user_id' => $iUserId], false);

            $this->_logTransaction(
                $iUserId,
                null,
                $iAmount > 0 ? 'bonus_grant' : 'bonus_revoke',
                abs($iAmount),
                $iActorUserId,
                $sNote
            );
        } finally {
            db()->select("RELEASE_LOCK('" . $sLockName . "') AS released")->from('DUAL')->execute('getSlaveRow');
        }
    }

    /**
     * @param int $iUserId
     * @param int $iLimit
     *
     * @return array newest first
     */
    public function getLedgerForUser($iUserId, $iLimit = 100)
    {
        return (array)db()->select('*')
            ->from(':hulahoot_swess_credit_transaction')
            ->where(['user_id' => (int)$iUserId])
            ->order('id DESC')
            ->limit(0, (int)$iLimit)
            ->execute('getSlaveRows');
    }

    /**
     * @param int $iUserId
     * @param int|null $iPostId
     * @param string $sType
     * @param int $iAmount
     * @param int|null $iActorUserId
     * @param string|null $sNote
     */
    private function _logTransaction($iUserId, $iPostId, $sType, $iAmount, $iActorUserId, $sNote)
    {
        db()->insert(':hulahoot_swess_credit_transaction', [
            'user_id' => (int)$iUserId,
            'swess_post_id' => $iPostId !== null ? (int)$iPostId : null,
            'type' => (string)$sType,
            'amount' => (int)$iAmount,
            'actor_user_id' => $iActorUserId !== null ? (int)$iActorUserId : null,
            'note' => $sNote !== null && $sNote !== '' ? substr((string)$sNote, 0, 255) : null,
            'created' => time(),
        ]);
    }
}
