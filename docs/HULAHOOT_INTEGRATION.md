# Main Hulahoot Integration — Architecture

Status: **architecture only — no publishing API implemented yet**, per explicit instruction. This
document describes the seam that's now in place and what plugs into it later.

## Why this exists

The Partner Portal (`partnershipportal.hulahoot.com`) was starting to feel like a standalone
product: subscribe, land in a wallet, click a placeholder "Create Promotion" button that went
nowhere in particular. That's backwards. The Portal's job is narrower than that — it's where a
business finds its Industry, buys a plan, and is granted the *right* to create promotions. The
promotions themselves are meant to end up on the main Hulahoot application
(`hulahoot.com`), not live here. This phase makes that direction explicit in the code, without
building the actual cross-application call (the main app's publishing API doesn't exist yet, so
there's nothing real to call).

## The flow this points toward

```
Find Your Industry → Industry → Choose Package
        ↓
Native phpFox purchase (Core Subscriptions — billing, gateway, renewal, all untouched)
        ↓
Entitlement (Service/Entitlement.php) — derived, not stored
        ↓
Promotion Composer (Phase 3, not built yet) — gated on entitlement
        ↓
Service/HulahootPublisher.php (stub — throws until Phase 3/4)
        ↓
Main Hulahoot application (hulahoot.com) — receives the published promotion
```

## What exists now

**`Service/Entitlement.php`** — answers "what is this user currently allowed to do," computed
live from `subscribe_purchase` (native — status, expiry) joined with `hulahoot_subscription_package`
(the Phase 2 companion row — purchase\_limit, campaign\_limit, posting limits, monthly\_credits).
Deliberately **not** a new table: phpFox already owns purchase state authoritatively, and
Phase 2 already owns the limits: introducing a third source of truth (a stored "entitlement"
row that could drift out of sync with either) would be the wrong kind of duplication this
project has avoided everywhere else. `getActiveEntitlement($userId)` returns `null` the moment
the user has no completed, unexpired purchase, or their package's Hulahoot rules are inactive —
same "no row = no entitlement, not an error" convention as everything else in this app.

**`Service/HulahootPublisher.php`** — the literal integration point. One public method,
`publish(array $aPromotionData)`, and it does exactly one thing right now: throws
`\RuntimeException`. Nothing calls it yet, because nothing that produces a finished promotion
exists yet. Its purpose is to exist *as a name in the codebase* — so when the Promotion Composer
is built (Phase 3) and the main application's publishing API is defined, the integration is a
change to this one file, not a redesign of where publishing logic lives.

**`/hulahoot/promotions/create`** (the Phase 0 placeholder route) now checks
`Entitlement::getActiveEntitlement()` before rendering. No active entitlement → a message and a
link back to `/find-your-industry`. Active entitlement → the same "coming soon" placeholder as
before, now showing the real plan/credits/limits pulled live from the purchase, proving the gate
is wired to real data rather than a hardcoded condition.

## What Phase 3+ adds (not built yet, on purpose)

- **The Promotion Composer itself** — the actual create-a-promotion UI. Doesn't exist. Everything
  above is the gate in front of a door that isn't hung yet.
- **A `hulahoot_promotion` table (or equivalent)** — `Entitlement::getActiveEntitlement()`
  currently hardcodes `promotions_used` / `campaigns_used` to `0` because there is nothing yet to
  count. Once promotions exist, those two lines become real `COUNT()` queries against whatever
  table stores them, and `purchase_limit` / `campaign_limit` become real enforcement rather than
  just numbers displayed on a page.
- **Credits as a ledger** — `monthly_credits` is still the Phase 1/2 configured-amount-only value.
  Turning it into an actual balance that goes up on renewal and down on spend is separate work,
  flagged the same way in every phase so far.
- **The real `HulahootPublisher::publish()` implementation** — once the main Hulahoot
  application defines what a publish request/response looks like (REST call, auth scheme,
  payload shape, error handling), that logic replaces the single `throw` in this class. Nothing
  else in the codebase should need to change when that happens — every caller already treats
  publishing as "call this method, handle the result," never as "build a promotion row and set a
  status flag by hand."

## What deliberately has not changed

- Core Subscriptions still owns billing, gateways, renewal, and user-group assignment entirely —
  `Entitlement` only ever *reads* purchase state, never writes it.
- No new duplicated storage — see "why not a table" above.
- No live network call to `hulahoot.com` or anywhere else. `HulahootPublisher` is a name and a
  contract, not a client.
