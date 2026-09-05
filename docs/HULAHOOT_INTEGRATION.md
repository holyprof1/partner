# Main Hulahoot Integration — Connection Plan

Status: **planning only — nothing in this document is built yet.** This
replaces an earlier version of this file that described a "Promotion
Composer" concept from before SWESS existed; that concept was superseded
entirely by SWESS and the old content no longer matched the codebase. This
version reflects what's actually here now and lays out the concrete plan for
connecting it to the main site, per the client's request to start that
planning.

## Why this is still a plan and not code

`hulahoot.com` (the main site) is a **separate Laravel application** — not
this phpFox install, not even the same framework. Confirmed directly: the
account's crontab has an entry hitting `www.hulahoot.com/cron.php`, and that
domain's docroot is a Laravel app (`artisan`, `bootstrap/`, `app/` at its
root) with no relation to `partnershipportal.hulahoot.com`'s phpFox codebase.
That means this integration is a real cross-application API call, not an
in-process function call — and nothing here can specify the other side of
that contract, because this repository has no visibility into the Laravel
app's code, routes, or auth scheme. Building against an assumed contract
would risk exactly what's explicitly off-limits: faking a successful publish
against an endpoint that doesn't exist yet.

## What exists today

- **`Service/HulahootPublisher.php`** — the seam. One public method,
  `publish(array $aPromotionData)`, which unconditionally throws
  `\RuntimeException`. Nothing calls it. This is intentional — see its own
  docblock.
- **`hulahoot_swess_post.status = 'published'`** — SWESS's own bookkeeping
  only. A post reaches this status via three call sites in `Service\Swess`:
  `submitPost()` (no review required, publish immediately), `approvePost()`
  (an admin approves a pending, non-scheduled post), and `publishDuePosts()`
  (a scheduled post's time arrives, run every 5 minutes via cron — see
  `publish-scheduled-swess-posts.php`). None of the three call
  `HulahootPublisher` today; "published" currently means nothing more than
  "SWESS itself considers this live."
- **`hulahoot_swess_post.feed_id`** — nullable, unused, reserved for
  whatever reference id the real integration ends up needing (originally
  imagined as a native `phpfox_feed` row id; may end up being the main
  site's own post id instead — see "open questions" below).

## The connection, concretely

**Where it plugs in**: the same three call sites that already transition a
post to `'published'` are the three places `HulahootPublisher::publish()`
should be called, immediately after the local status update commits. All
three already have the full post row in hand at that point, so no new
lookup is needed — this is a single new step at the end of an existing
transition, not a redesign of the lifecycle.

**Proposed payload** — built from columns that already exist on
`hulahoot_swess_post`, nothing invented:
```
{
  "swess_post_id": 123,               // this system's id, for idempotency (see below)
  "publisher": {
    "identity_type": "self" | "page",
    "identity_id": 45                 // hulahoot_profile.profile_id, or a native Page id
  },
  "content": "...",
  "disclosure_tag": "Sponsored",      // hulahoot_swess_tag.name for the post's tag_id
  "target": {
    "type": "city" | "state" | "country" | "continent" | "site_wide",
    "value": "...",                   // distribution_target_value, opaque today - see open questions
    "label": "Lagos, Nigeria"         // distribution_target_label, human-readable
  },
  "published_at": 1786945000
}
```

**Delivery tracking** — genuinely not built yet, proposed only. `'published'`
today conflates two different facts: "SWESS approved this" and "the main
site has it." Those need to stay separable, the same way this codebase has
kept every other native/companion pair separate rather than overloading one
column with two meanings. The likely shape: a nullable
`hulahoot_swess_post.synced_to_hulahoot_at` timestamp (or a small
`hulahoot_swess_post_delivery` companion row if more than a timestamp needs
tracking - a response id, a retry count, a last-error message). `'published'`
continues to mean what it means today; delivery success/failure is tracked
alongside it, not instead of it. Not adding this column now, since its exact
shape depends on what the real API actually returns.

**Retry** — once delivery tracking exists, a dedicated CLI script following
the exact same precedent as `publish-scheduled-swess-posts.php` (its own
crontab entry, since nothing here relies on phpFox's own inert cron table)
that finds `published_at IS NOT NULL AND synced_to_hulahoot_at IS NULL` rows
older than some threshold and retries them, capped and backed off the same
way a webhook consumer would be expected to.

## What has to come from the main site (Laravel) side before any of this can be built

Not decisions this codebase can make on its own:

1. **The actual endpoint** — URL, HTTP method, request/response shape. The
   payload sketch above is a proposal built from what SWESS already has, not
   a confirmed contract.
2. **Authentication** — a shared API key/secret is the simplest fit for two
   applications owned by the same client, but that's a proposal, not a
   decision made here.
3. **Idempotency contract** — does the main site dedupe by `swess_post_id`
   if this codebase sends it and retries on a timeout? Needs to be agreed,
   not assumed.
4. **What a published SWESS item actually becomes on the main site** — a
   Feed post, a distinct "Partner Promotion" content type, something else
   entirely? This is a product decision for the client, not something to
   infer from the Partner Portal's own schema.
5. **Target resolution** — `distribution_target_value` is currently an
   opaque, provider-neutral value (a continent code, `phpfox_country.
   country_iso`, `phpfox_country_child.child_id`, or an unresolved city
   value) by design, because the centralized Hulahoot Location Service that
   would resolve "city" targeting doesn't exist in this codebase and was
   explicitly scoped to the main Hulahoot system in an earlier phase. Whether
   the main site resolves that value itself or expects it pre-resolved needs
   an answer from whoever owns that service.
6. **Failure semantics** — what should happen locally if the main site
   rejects a publish (validation error) versus times out (transient)? The
   retry design above assumes transient-only; a hard rejection likely needs
   its own local status (`'failed'` already exists in the lifecycle for
   exactly this, currently unused since nothing calls out yet).

## What this phase deliberately did not do

- No new schema (`synced_to_hulahoot_at` etc. is a proposal, not a migration).
- No HTTP client, no guessed endpoint, no invented credentials.
- No change to `HulahootPublisher::publish()` — it still throws, honestly,
  until there's a real contract to implement against.
- No change to what `'published'` means in `hulahoot_swess_post` today.

## Next action

Once the client/main-site team can answer the six open questions above, the
actual build is small and localized: three call sites already exist, the
payload is already sketched from real columns, and the retry pattern already
has a working precedent in this exact codebase. Implementing
`HulahootPublisher::publish()` for real, adding delivery tracking, and
wiring the three call sites is the entire remaining scope - no other part of
SWESS needs to change when that happens.
