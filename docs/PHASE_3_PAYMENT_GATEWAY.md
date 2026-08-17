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

## Follow-up pass: real-HTTP verification + a genuine bug found and fixed

A later pass re-verified everything above through actual authenticated HTTP
requests (not just service-layer calls) - a throwaway member, an unrelated
"victim" member, and a throwaway admin, each given a real, working login
cookie (constructed via phpFox's own `Phpfox_Hash::setRandomHash()` against
the account's real stored password/salt - not a workaround, the same hash a
real login produces). Building this surfaced three environment quirks worth
recording for any future test account:

- A freshly `INSERT`ed `phpfox_user` row needs matching `phpfox_user_count`
  and `phpfox_user_field` rows too - `User_Service_Auth`'s own user lookup
  query **INNER JOINs** both (not `LEFT JOIN`), so a raw-inserted user
  without them is silently excluded from the query with no error, and the
  login looks like a bad password.
- `user_group_id = 3` is **`GUEST_USER_ID`**, not a normal member -
  `NORMAL_USER_ID = 2`. A test "member" needs group 2.
- `view_id` must be `0`. Values `1`/`2` trip a real, separate gate in
  `Auth`'s post-construction status check (`in_array(getUserBy('view_id'),
  [2, 1])`) that redirects to `/user/pending/` - i.e. "awaiting approval" -
  regardless of whether the password/cookie themselves are valid.

With that working, every item from the prior pass's "not independently
verified" list was re-tested for real: AdminCP Approval Queue and Whitelist
screens (real Smarty-rendered pages, correct content), a genuine cross-account
ID-manipulation attempt (an authenticated "victim" SWESS user trying to
cancel/edit another authenticated user's post by URL id alone - cleanly
rejected, target post unaffected), the old "Manage Scheduled Posts" link's
removal (confirmed absent from a real authenticated page load - the earlier
pass's belief that it was still present turned out to be a grep matching this
doc's own removal comment, not a real link), a genuine two-process concurrent
approval race against the same pending post (exactly one succeeded, the other
got the correct "Only a pending post can be approved" error), and SWESS
notifications (confirmed created with the correct `type_id` and recipient).

**A real, previously-undetected bug surfaced doing this: `Core_Pages` (the
native Pages app) is `is_active = 0` on this install.** The earlier
`approveIdentity()` Page-ownership fix called `Phpfox::getService('pages')`
unconditionally - correct in principle, but calling a service on an inactive
app doesn't return null, it hard-errors, so every real attempt to approve a
Page identity was crashing with an uncaught 500 instead of a clean rejection.
Fixed by checking `Phpfox::isAppActive('Core_Pages')` first and failing the
same clean, catchable way every other validation failure in that method
already does. This also means: **Business/Page-identity SWESS publishing is
not usable at all on this install right now** - not a SWESS bug, a pre-existing
platform state (Pages was already off before any of this work started) - only
`post_as_self` is currently exercisable. The positive path (a real owned Page
successfully approved) could not be fully tested end-to-end for this reason;
every rejection path (nonexistent page, page owned by someone else, Pages
inactive) was confirmed correct.

## The three payment concerns - can they be fixed without touching native files?

Investigated concretely (not just theoretically) by reading every candidate
hook point in `Purchase\Process::update()` and `Callback::paymentApiCallback()`
line by line.

**1. Duplicate/replayed callbacks (idempotency).** No plugin hook exists
before `paymentApiCallback()` calls `Purchase\Process::update()`, and no hook
exists inside `update()`'s `'completed'` case before its side effects run -
the one hook that does exist there (`subscribe.service_purchase_process_
update_pre_log`) fires *after* the status flip and the auto-cancel loop, too
late to prevent anything. **Not fixable at the application level as
prevention.** Partially mitigated already, though: `syncPackageEntitlement()`
is itself idempotent (confirmed by test - calling it twice back to back never
creates a second whitelist row or double-grants), so however many times a
duplicate native callback fires, Hulahoot's own SWESS entitlement layer stays
correct regardless. What stays unmitigated is native-side noise a duplicate
callback still causes today - a second confirmation email, a second history
row, and (see #2) a second unwarranted auto-cancel pass.

**2. Auto-cancel of a user's other completed purchases.** Same finding - the
auto-cancel loop is unconditional, inline, with no hook point before it runs.
Only the path Hulahoot fully controls (`completeAsHulahoot()`, the free/
admin-preview path) avoids it, by not calling `update()` at all. A real
gateway's callback has no equivalent - it calls native `update()` directly.
**Not fixable at the application level.**

**3. Webhook/signature verification.** This lives inside each gateway
driver's own `callback()` method - one file per gateway
(`PF.Base/include/library/phpfox/gateway/api/*.class.php`), not a shared
choke point. PayPal's already does real verification; Stripe's (via the
bundled `ynadvancedpayment` addon) doesn't check the signature at all. Whether
this is fixable app-side depends entirely on which gateway gets chosen - not
something to build blind.

**None of the three were patched.** No safe overlay point exists for any of
them, zero gateways are active so there is no live risk today, and patching
without a real gateway to test against risks a worse failure than the current
state (silently breaking legitimate purchase completion). If/when a gateway is
chosen, the smallest correct native change for #1 and #2 is a single guard
clause at the very top of `paymentApiCallback()`:
```php
if ($aPurchase['status'] === 'completed') { return true; } // already processed
```
placed before `Purchase\Process::update()` is called - this alone closes both
the duplicate-processing and (as a side effect, since a second call would no
longer reach `update()` at all) the repeat-auto-cancel risk for a replayed
callback. It does not fix auto-cancel on a *first* legitimate completion for a
user who already holds another completed purchase - that still needs either a
native change to skip the auto-cancel loop for Hulahoot's packages
specifically, or accepting that a real gateway purchase currently cannot
safely coexist with an existing completed purchase the way the free path
already does. This is exactly the kind of native change that should not be
made silently - it's documented here, not applied, pending a real gateway
decision and a sandbox to test it against.

## Gateway integration checklist (for whenever a gateway is chosen)

Not a recommendation of which gateway to use - that's a business decision.
This is what actually has to happen, in order, once one is picked.

**1. Which gateway is currently available on this install.**
None. Every row in `phpfox_api_gateway` (PayPal, Stripe, Braintree, BitPay,
CC Bill, GoPay, iTransact, Skrill, WebMoney, Authorize.Net) is `is_active = 0`.
No Paystack or Flutterwave driver exists in this codebase at all - if the
client wants either, that driver doesn't exist yet and would need to be
built (or a phpFox marketplace addon sourced), separate from everything in
this document.

**2. What configuration it requires.**
- AdminCP → API → Gateway → click the chosen gateway → enter its real
  credentials (API key/secret, merchant id - varies per gateway) → set
  Active = Yes. Test mode (`is_test`) should be exercised first if the
  gateway supports a sandbox.
- Confirm `core.site_title` and the currency (`subscribe_package.default_
  currency_id` for Hulahoot's packages) match what the gateway account
  expects.
- If the chosen gateway needs a webhook/IPN URL configured on *their* side
  (most do), that URL is native and gateway-specific - found inside that
  gateway's own driver class under `PF.Base/include/library/phpfox/gateway/
  api/`.

**3. What code path handles the callback.**
Gateway's hosted checkout → returns the buyer to native `subscribe.complete`
(cosmetic redirect only) → gateway's own async server-to-server callback hits
route `api.gateway.callback` → `Api_Component_Controller_Gateway_Callback` →
`Api_Service_Gateway_Gateway::callback($gatewayId)` → that gateway's driver's
own `callback()` method → `Phpfox::callback('subscribe.paymentApiCallback', $params)`
→ `Apps\Core_Subscriptions\Service\Callback::paymentApiCallback()` →
`Apps\Core_Subscriptions\Service\Purchase\Process::update()`. Entirely native,
end to end - Hulahoot code is never in this call chain. `syncPackageEntitlement()`
picks up the result afterward, lazily, the next time the buyer loads any
`/hulahoot/swess/*` page (see this doc's own architecture section above).

**4. What must be verified before this is trusted with real money.**
- The chosen gateway's driver actually verifies the callback is genuine
  (signature/IPN-verification, not just trusting whatever hits the URL) -
  confirmed already true for PayPal, confirmed already **false** for Stripe
  as currently wired.
- The idempotency guard from this doc's "three payment concerns" section is
  in place, so a retried/duplicate webhook (every major gateway retries on a
  timeout or non-200 response) can't double-process.
- A decision on the auto-cancel-other-purchases behavior for a real gateway
  purchase - test with a user who already holds one completed purchase
  before adding a second, paid one, and confirm the outcome matches what the
  client actually wants (native default: the older one gets silently
  cancelled).
- `hulahoot_subscription_package.swess_enabled` is set correctly on every
  package that should grant SWESS, and confirmed unset on every package that
  shouldn't (AdminCP → Hulahoot → Subscription Packages → Edit → "Include
  SWESS").
- `Core_Pages` is reactivated first if any package/flow is meant to support
  `post_as_business` - it's currently off, independent of anything payment-related.

**5. What must be tested before going live.**
- A real successful payment in the gateway's sandbox/test mode, end to end:
  checkout → callback received → `subscribe_purchase.status = 'completed'`
  → correct `hulahoot_subscription_package` limits visible in `/hulahoot/
  swess/entitlement` → (if `swess_enabled`) SWESS access appears on next
  page load without any admin action.
- A failed payment (declined card, insufficient funds, etc.) - confirm no
  purchase is marked completed and no SWESS access is granted.
- A cancelled payment (buyer abandons checkout) - confirm the purchase stays
  `pending`/uncompleted, not silently treated as paid.
- A duplicate/replayed webhook for the same transaction (most gateway test
  consoles can manually resend a webhook event) - confirm no double-charge
  bookkeeping, no double SWESS grant, no repeated confirmation email once
  the idempotency guard is in place.
- A user who already holds one completed purchase completing a second, real
  gateway purchase - confirm the outcome matches whatever was decided in
  item 4 above, not just "whatever native code happens to do."
- Full regression on the free-package path (`completeAsHulahoot()`) - this
  document's changes must not have altered that path's existing behavior.

This entire checklist is blocked on a gateway decision - nothing in it can be
executed further without real credentials, which were deliberately not
invented for this phase.
