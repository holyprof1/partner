# Phase 1 — Subscription Integration

Status: complete and verified live. Scope: navigation/status-display integration between the SWESS Wallet profile tab and phpFox's native Subscription module (`Core_Subscriptions`). No SWESS entitlement, Credits, Payments, gateway, or dashboard-redesign work is included - see "Explicitly out of scope" below.

## 0. Decision: does the profile page remain the temporary dashboard?

**Yes, unchanged.** The profile page (`/username`) continues to carry the native feed/composer exactly as it did before Phase 0 and Phase 1. The "dashboard" is the **SWESS Wallet tab** (`/username/hulahoot`), a separate profile sub-section reached via the existing tab bar - not a replacement of the profile's default view. Nothing about the profile's default tab was touched in this phase. The dashboard-redesign question (collapsing the profile into a business workspace, hiding the native feed) is unstarted and awaits separate sign-off, per the standing instruction not to touch the native feed or composer yet.

## 1. Audit of Core_Subscriptions (`PF.Site/Apps/core-subscriptions`)

### App registration (`start.php`)
```php
Phpfox::getLib('module')
    ->addAliasNames('subscribe', 'Core_Subscriptions')
    ->addServiceNames([...])
    ->addTemplateDirs(['subscribe' => .../core-subscriptions/views])
    ->addComponentNames('controller', [...])
    ->addComponentNames('ajax', ['subscribe.ajax' => Ajax\Ajax::class])
    ->addComponentNames('block', [...]);
```

### Packages
- Table: `subscribe_package`. Admin-configurable via `subscribe.admincp.*` controllers - no package names, prices, or periods are hardcoded anywhere in phpFox or in this integration.
- Read via `Service\Subscribe::getPackages($bIsForSignUp, $bShowAllSubscriptions, $bGetAll)` - filters by `is_active`/`is_removed`/visibility group, resolves multi-currency cost, formats recurring-period phrases.
- **Confirmed empty on this install** - zero packages configured. This integration was verified using a temporary, manually-inserted test package/purchase (created and deleted during verification - see §6).

### Purchase flow
1. User reaches `/subscribe` (`Controller\IndexController`) or `/subscribe/register/{id}` (`Controller\RegisterController`), which renders the `subscribe.upgrade` block (`Block\UpgradeBlock`).
2. `UpgradeBlock::process()` creates a `subscribe_purchase` row via `Service\Purchase\Process::add()`.
3. **Free packages**: marked `completed` immediately in the same request; template shows a success state in place (no redirect).
4. **Paid packages**: a payment gateway is loaded with `'return' => makeUrl('subscribe.complete')`; the user completes payment off-site and returns to `/subscribe/complete`.

### Renewal flow
- `renew_type` column on `subscribe_purchase` (0 = one-time/manual, recurring types per gateway).
- `Service\Purchase\Process::updateRenewType()`, `recurringWithPoint()`, `checkPurchaseExpirationByManualMethod()`, `notifyUserWithManualMethodPayment()` handle recurring billing and manual-renewal reminders.
- `Service\Purchase\Purchase::_build()` computes `show_renew` (true inside the admin-configured notify window before/after `expiry_date`) - the native "show a renew button" signal, not duplicated in Hulahoot's own code.

### Expiration
- `subscribe_purchase.expiry_date` (unix timestamp; `0` = non-expiring/one-time).
- `Service\Purchase\Process::downgradeExpiredSubscribers()` - the native cron-driven job that reverts a user's group once expired. Hulahoot does not touch this.
- `Service\Purchase\Purchase::getExpireTime($purchaseId)` / `getPurchase($id, true)`'s computed `new_expiry_date` - available for a future "renew extends your date by X" display; not used in Phase 1.

### User group assignment
- `Service\Purchase\Process::update($purchaseId, $packageId, 'completed', $userId, $userGroupId)` calls **`Phpfox::getService('user.process')->updateUserGroup($userId, $userGroupId)`** - the same core service every other group-changing feature on the platform uses. Confirmed by reading the method body directly.
- On completion, any previous `completed` purchase for that user is auto-cancelled (`subscribe_cancel_reason` + purchase status set to `cancel`) - a user has at most one active subscription at a time. This is why Phase 1's status lookup can safely assume "the most recent `completed` row is the current plan."

### Existing routes (native, unchanged)
| Path | Controller |
|---|---|
| `/subscribe` | `subscribe.index` |
| `/subscribe/list` | `subscribe.list` |
| `/subscribe/register/*` | `subscribe.register` |
| `/subscribe/view/*` | `subscribe.view` |
| `/subscribe/compare` | `subscribe.compare` |
| `/subscribe/complete` | `subscribe.complete` |
| `/subscribe/renew-method` | `subscribe.renew-method` |
| `/subscribe/admincp/*` | AdminCP package/reason management |

### Existing controllers (native, unchanged)
`IndexController`, `ListController`, `RegisterController`, `ViewController`, `CompareController`, `CompleteController`, `RenewMethodController`, plus `Controller\Admin\*` for AdminCP.

### Existing templates (native, unchanged)
`views/controller/*.html.php`, `views/block/*.html.php` under `core-subscriptions` - none were edited or overridden.

## 2. Cleanest integration point

**`Apps\Core_Subscriptions\Service\Purchase\Purchase::getMySubscriptions($userId, ['status' => 'completed'], 1, 1)`** - the exact service method the native "My Subscriptions" page itself calls. Wrapped in one new Hulahoot service (`Service\Subscription::getStatusForUser()`) that does nothing but call it and reshape the result for the template. No new database queries against `subscribe_*` tables were written - every read goes through Core_Subscriptions' own service layer.

Also used, indirectly, as supporting evidence during the audit (not called at runtime by Hulahoot):
- `Service\Subscribe::getCurrentUsingPackageForCompare()` - considered and rejected in favor of `getMySubscriptions()`, which additionally returns `expiry_date` without a second query and doesn't exclude purchases of since-deactivated packages (a user who already bought a plan should still see it even if admin later disables it for new signups).

## 3-4. "No Active Plan" / "Choose Plan" and the native purchase flow

- "Choose Plan" and "Upgrade / Renew" are both plain links to **`/subscribe`** - the native package list. No Hulahoot-owned purchase UI, form, or gateway call exists. This satisfies "do not duplicate phpFox functionality" as literally as possible: there is no Hulahoot purchase code to duplicate anything with.

## 5. Post-purchase redirect - confirmed constraint, not implemented

Traced `Controller\CompleteController::process()` in full (4 lines):
```php
public function process()
{
    $this->url()->send('subscribe');
}
```
This is unconditional - no request data, session value, or hook is consulted before the redirect, and `Phpfox_Url::send()` calls `exit()` immediately, so the controller's own `clean()` hook (`subscribe.component_controller_complete_clean`) never fires (code after `exit()` cannot run). No plugin hook exists earlier in this path either. This was verified by reading the method, not assumed.

**Conclusion:** there is no supported, non-core-editing way to change where a *gateway-based* purchase lands afterward. The two theoretical options were rejected:
- Editing `CompleteController.php` - forbidden this phase (core-app modification).
- Registering a competing Core\Route for `/subscribe/complete` to shadow the native one - technically possible, but this duplicates/overrides native routing rather than integrating with it, which conflicts directly with "do not duplicate phpFox functionality" and would be fragile (depends on route-registration order between two apps).

**What this means in practice today:** a free-package purchase completes in place (no redirect at all - the register page shows a success state) and the user can navigate back to their dashboard tab normally. A paid/gateway purchase lands on the native `/subscribe` package list after payment, not automatically back on the profile. Getting back to the dashboard from there is one click via the normal site header, not automatic.

**This is a Phase 2 decision point**, not a bug: either accept native behavior, or get sign-off for a scoped, explicit exception to the core-modification rule for this one method.

## 6. SWESS Wallet - what was built

### New files (all inside `PF.Site/Apps/hulahoot/`)
| File | Purpose |
|---|---|
| `Service/Subscription.php` | `getStatusForUser($userId)` - thin read-only wrapper over `subscribe.purchase`'s `getMySubscriptions()`. No writes, no entitlement logic. |
| `Controller/WalletController.php` | Backs the SWESS Wallet tab. Renders subscription status; **renamed from `Controller/ProfileRedirectController.php`** (Phase 0's placeholder-redirect version, removed and backed up as `.bak_replaced_by_WalletController`). |
| `views/controller/profile.html.php` | The Wallet page template - Current Plan / Status / Expiration / Upgrade-Renew, or No Active Plan / Choose Plan, plus the static Credits placeholder. |

### Modified files
| File | Change |
|---|---|
| `start.php` | One line: `'hulahoot.profile'` now maps to `WalletController` instead of `ProfileRedirectController`. No routes, no URLs changed - `/username/hulahoot` is unchanged from Phase 0. |
| `phrase.json` | +9 keys, all `hulahoot_`-prefixed (plan/status/expiration/buttons/credits labels). |

### Hooks
None new this phase. The Phase 0 hook (`hooks/profile.template_block_pic_info.php`, the "Create Promotion" button) is untouched and still works - confirmed during verification.

### Placeholders (exactly as scoped, nothing more)
- **Credits**: static string "Credits will appear here." - no table, no service, no logic.
- **SWESS entitlement**: not referenced anywhere in this phase's code.
- **Payments / gateway**: not touched; Hulahoot code never calls a gateway or writes to `subscribe_purchase`.

## 7. Verification performed

All against the live site, via real HTTP requests (not assumed from code reading alone):

| Check | Result |
|---|---|
| Homepage + Wallet page load after deploy | Both `200`, single `<html>` document each |
| No-plan state | "No Active Plan" + "Choose Plan" → `/subscribe`, confirmed via curl and screenshot |
| Native `/subscribe` page still loads | `200`, single document |
| With-plan state | Temporary test package + `completed` purchase inserted → Wallet correctly showed "QA Test Plan", status "completed", "September 5, 2026" expiration (formatted via the same date library Core_Subscriptions itself uses), "Upgrade / Renew" button - confirmed via curl and screenshot, then **test data fully deleted**, confirmed reverted to "No Active Plan" |
| AdminCP Profile Type / Category pages | Unaffected, `200` |
| "Create Promotion" button (Phase 0) | Still present, unaffected |
| `main.log` | No new errors introduced by this phase (only pre-existing entries from an earlier, already-fixed incident) |

One real bug was found and fixed *during* verification, not in Hulahoot's code: the manually-inserted test package initially had `fail_user_group = 0` (schema default), and `getMySubscriptions()`'s native query does an `INNER JOIN` against `user_group` on that column - silently excluding the row. Fixed by setting a valid group id on the test row. Documented here because it's a real, reusable fact about the native query's behavior: **any package row needs a valid `fail_user_group`, or purchases against it will silently disappear from `getMySubscriptions()`.**

## 8. What remains for Phase 2

- Product decision on the post-purchase redirect constraint (§5).
- Real packages configured in AdminCP (none exist yet on this install).
- SWESS entitlement logic.
- Credits (balance, earning, spending).
- Payments / gateway configuration and testing.
- Dashboard redesign (replacing the native feed on the profile's default tab) - explicitly deferred, not started.
- Wiring "Create Promotion" (Phase 0 placeholder) to require/check an active subscription, if that turns out to be the intended gating.
