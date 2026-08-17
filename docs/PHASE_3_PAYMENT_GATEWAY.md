# Phase 3: Payment Gateway → Subscription → SWESS Entitlement

What this phase actually connected, what it deliberately did not touch, and
why — written for whoever picks up gateway activation next.

## What was asked

Connect the existing AdminCP Payment Gateway (`admincp/api/gateway`) to the
existing Subscription/Package purchase flow, so that a qualifying package
purchase automatically grants SWESS access without an admin manually
whitelisting the buyer — reusing the existing architecture, never faking
payment verification, never duplicating a payment or subscription system.

## What was found (read before changing anything)

- **Every payment gateway on this install is currently `is_active = 0`.**
  No Paystack, no Flutterwave, no bank transfer — nothing is live. The
  gateway list is the native phpFox `api` module
  (`PF.Base/module/api/`, table `phpfox_api_gateway`), reached from
  AdminCP → API → Gateway.
- The **real** completion path for a paid purchase is entirely native and
  entirely outside this app: `Apps\Core_Subscriptions\Controller\
  RegisterController` (gateway selection) → a gateway driver under
  `PF.Base/include/library/phpfox/gateway/api/*.class.php` → the async
  callback route `api.gateway.callback` → `Apps\Core_Subscriptions\Service\
  Callback::paymentApiCallback()` → `Apps\Core_Subscriptions\Service\
  Purchase\Process::update()`, which actually marks the purchase
  `'completed'`.
- **Two real, pre-existing problems live in that native path**, independent
  of anything Hulahoot has ever built:
  1. `Purchase\Process::update()`'s `'completed'` branch unconditionally
     cancels every *other* completed purchase the same user holds. That's
     why `Service\PurchaseFlow::completeAsHulahoot()` already exists and
     deliberately skips it for the free/admin-preview path — but the real
     gateway path still goes through `update()` directly, auto-cancel and
     all.
  2. `paymentApiCallback()` has no idempotency guard (a duplicate/replayed
     callback re-runs the full completion path, including the auto-cancel
     loop) and the Stripe driver (via the bundled `ynadvancedpayment`
     addon) never verifies the webhook signature - it trusts whatever JSON
     body is POSTed to it. PayPal's driver, by contrast, does real IPN
     verification.
- No plugin hook exists inside `paymentApiCallback()` or `Purchase\
  Process::update()` before their side effects run, so there is no safe
  overlay point to intercept the real gateway path without editing native
  Core_Subscriptions files directly.
- `hulahoot_subscription_package` had no "does this package include X"
  flag of any kind before this phase - every capability was a numeric
  limit column.

## What this phase built

1. **`hulahoot_subscription_package.swess_enabled`** (new column) - an
   admin flags a package as SWESS-qualifying. Editable from the existing
   package edit screen (AdminCP → Hulahoot → Subscription Packages →
   Edit), no new admin screen.
2. **`hulahoot_swess_whitelist.granted_by_package_id`** (new, nullable
   column) - NULL means the row is admin-managed; a package id means
   `Service\Swess::syncPackageEntitlement()` created or last touched it.
   Every AdminCP save (`SwessWhitelistAddController`) always resets this
   back to NULL, so an admin's own configuration always wins from that
   point on, regardless of what the user goes on to purchase.
3. **`Service\Swess::syncPackageEntitlement($userId)`** - a lazy,
   idempotent reconciliation, not a one-shot event handler. It:
   - grants (creates or re-enables) a SWESS whitelist row when the user's
     current active entitlement's package has `swess_enabled = 1`,
   - never touches a row where `granted_by_package_id IS NULL`
     (admin-owned),
   - never auto-revokes - if the qualifying entitlement later expires or
     the admin edits the row directly, this method leaves it alone rather
     than silently pulling access.
4. **Two call sites**, chosen specifically because no native hook exists:
   - `PurchaseFlow::completeAsHulahoot()` calls it immediately - this is
     the one completion path Hulahoot code fully controls (free packages,
     and admin-preview purchases with no gateway active).
   - Every `/hulahoot/swess/*` member route also calls it on page load, as
     a safety net. This is what makes a *real* paid-gateway purchase (once
     one is ever activated) end up with correct SWESS access too - the
     buyer just sees it applied on their next SWESS page load rather than
     at the instant the gateway's callback fires, since this app never
     hooks into that native callback.
5. Closed a real, unrelated gap found while reading `approveIdentity()`
   for this work: the `'page'` identity branch did zero validation that
   the claimed Page id exists or is actually managed by the user being
   approved - any integer was accepted. Now validated via `Phpfox::
   getService('pages')->getPage()` + `isAdmin()`, the same native check
   the Pages app itself uses.
6. Added `GET_LOCK`-based concurrency guards (same pattern `PurchaseFlow::
   initiate()` already used per-package) to `submitPost()`/`approvePost()`/
   `rejectPost()`/`cancelPost()`, and a self-approval guard on approve/
   reject. Verified with a real two-OS-process race against the same
   draft post - exactly one submission wins, the other gets a clean
   "already submitted" error.
7. SWESS lifecycle notifications via phpFox's native declarative
   `Core\App\App::$notifications` mechanism (the same one Ync_Blogs uses
   for its own comment notification) - `whitelist_enabled/disabled`,
   `identity_approved/revoked`, `post_submitted/approved/rejected/
   published/failed`.
8. `Service\Swess::publishDuePosts()` + a new standalone CLI script,
   `publish-scheduled-swess-posts.php`, with its own crontab entry
   (`*/5 * * * *`) - phpFox's own native cron table (`phpfox_cron`) has
   **nothing triggering it at the OS level on this domain at all** (the
   only crontab entry on this box hits a completely different, unrelated
   Laravel app at `www.hulahoot.com`); this follows the exact precedent
   already established by `send-expiry-reminders.php`'s own dedicated
   crontab line rather than inventing a new pattern.

## What this phase deliberately did NOT do

- **Did not activate any payment gateway.** None were configured with real
  credentials, and doing so is a business/ops decision (which gateway a
  Nigerian client actually wants - none of the ten built-in drivers are
  Paystack or Flutterwave) outside this phase's authority.
- **Did not patch native `Core_Subscriptions` files** (the auto-cancel
  side effect, the missing idempotency check, Stripe's missing webhook
  signature verification). No safe plugin hook exists to fix these as an
  overlay without editing shared framework code directly, and with zero
  gateways active there was no live risk to fix urgently. This is real,
  pre-existing debt independent of Hulahoot - flagging it here rather than
  quietly patching a shared native app is the more honest and reversible
  choice.
- **Did not build auto-revoke-on-expiry.** A user auto-granted SWESS keeps
  it even if their qualifying purchase later expires, until an admin acts.
  Judged safer than silently pulling access mid-use; revisit if the client
  wants strict expiry enforcement.

## Before any real gateway goes live

1. Decide which gateway(s) the client actually wants (none of PayPal/
   Stripe/Braintree/BitPay/CCBill/GoPay/Heidelpay/iTransact/Skrill/
   WebMoney may be it).
2. Whichever is chosen, verify (or add) real signature/webhook
   verification in its driver - confirmed already present for PayPal,
   confirmed **absent** for Stripe as currently wired via the
   `ynadvancedpayment` addon.
3. Add an idempotency check to `paymentApiCallback()` (skip re-processing
   if the purchase is already `'completed'`) before the auto-cancel loop
   runs - or, per this phase's "never edit native files" boundary,
   determine whether a plugin hook can be added to `Purchase\Process::
   update()` first specifically for this purpose.
4. Decide how to handle the auto-cancel-other-purchases side effect for a
   real gateway purchase, the same way `completeAsHulahoot()` already
   decided for the free path - Hulahoot's multi-purchase model (a
   Founding Industry Partner holding several completed purchases at once)
   is incompatible with native `Purchase\Process::update()`'s default
   behavior.
5. `syncPackageEntitlement()`'s lazy safety net will then correctly apply
   SWESS access from a real paid purchase with no further Hulahoot-side
   changes needed.
