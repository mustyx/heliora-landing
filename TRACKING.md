# Tracking & attribution — runbook

Closes the P0 measurement gate in the H2 2026 Meta Campaign Strategy
(Section 10). Written 9 August 2026.

---

## What was broken

| Symptom | Consequence |
|---|---|
| `fbq('track','Lead')` fired in the browser immediately before `window.location.assign('thank-you.html')` | The navigation cancelled the request much of the time. Meta saw a fraction of real leads. |
| No Conversions API at all | No server-side backstop. Ad blockers and iOS ITP silently removed the rest. |
| `thank-you.html` fired GA4 `generate_lead` on **every page load** | A refresh, a back-navigation or a bookmarked visit each counted as a new conversion. It also double-counted against the event on the form page. |
| `thank-you.html` initialised the Meta pixel but never fired anything | The page was invisible to Meta — unusable for audiences or funnel verification. |
| No `event_id` anywhere | Deduplication was impossible even in principle. |
| UTM capture limited to source / medium / campaign | Could not compare ads, placements or creatives — only campaigns. |

At NGN 3.0M the account produces roughly 3–4 leads a week. Losing an
unknown fraction of those to a race condition, while inflating the rest
with refreshes, makes the numbers not merely imprecise but unusable.

## How it works now

```
visitor submits
      │
      ▼
submit-lead.php  ── validates, saves to MySQL
      │             generates lead_uid (32 hex) + event_id (uuid v4)
      │
      ├─► Meta Conversions API      Lead, event_id, hashed em/ph/fn/ln,
      │   (server, authoritative)   fbp, fbc, IP, user agent
      │
      └─► JSON { success, event_id, lead_uid }
                  │
                  ▼
            browser fires fbq('track','Lead', …, { eventID: event_id })
                  same id ──► Meta deduplicates ──► ONE conversion
            browser fires gtag generate_lead, transport_type 'beacon',
                  transaction_id = lead_uid
                  │
                  ▼
            250 ms pause, then redirect to thank-you.html
            (PageView only — no Lead, no generate_lead)
```

**The server is the source of truth.** If the browser event never leaves,
the conversion is still recorded. If both arrive, `event_id` collapses
them into one.

**Never generate `event_id` in the browser.** Two ids means one lead
counted twice, which is worse than the bug we just fixed.

## ViewContent — the optimization event

Section 06 optimises delivery on ViewContent rather than Lead, because
Lead volume reaches only ~7% of Meta's 50-events-per-week learning
threshold at this budget. ViewContent runs at roughly 106/week.

Fires **once per session**, on whichever comes first:

- the visitor opens the consultation modal (`form_open`), or
- the visitor reaches the services section **and** has been on the page
  at least 15 seconds (`services_section`).

The dwell-time gate is the whole point. A ViewContent that fired on page
load would be a landing-page view under another name, and would train
delivery toward exactly the weak traffic the strategy is trying to avoid.

---

## Deploy — three steps, in order

### 1. Run the database migration

cPanel → phpMyAdmin → select the leads database → **SQL** tab → paste
`migrations/2026-08-09-tracking.sql` → Go.

Safe to run more than once. It ends with a SELECT that should return
**16 rows** — if fewer, it did not complete.

> Do this **before** deploying the code. `submit-lead.php` writes to the
> new columns; if they don't exist yet, every submission fails.

### 2. Add the Conversions API token on the server

Events Manager → Data sources → pixel `1071599118775013` → Settings →
Conversions API → **Generate access token**.

Then in cPanel → File Manager → `config/config.php` on the server:

```php
define('META_CAPI_TOKEN', 'PASTE_THE_TOKEN_HERE');
```

`config/config.php` is gitignored and excluded from the deploy rsync, so
the server copy is the only place this lives. It is never committed and
never overwritten by a deploy.

Until the token is set, `sendMetaLeadEvent()` returns `skipped_config`
and logs nothing to Meta. Everything else — database, emails, Zoho, the
browser pixel — carries on working. The site will not break.

### 3. Deploy the code

Double-click `push-live.bat`.

---

## Verify — do this before spending anything

1. In `config/config.php` set `META_TEST_EVENT_CODE` to the code shown in
   **Events Manager → Test events**.
2. Submit a real test lead through the live form, with cookies accepted.
3. Check all four places. They must agree:

| Where | Expect |
|---|---|
| Events Manager → Test events | **One** Lead. Server and Browser both listed against it, marked deduplicated — not two separate Leads. |
| GA4 → Realtime | **One** `generate_lead`. Refresh thank-you.html a few times: the count must **not** move. |
| phpMyAdmin → `leads` | Newest row has `lead_uid`, `event_id`, `consent_state='accepted'`, `capi_status='sent'`. |
| Inbox | Auto-responder and admin notification both arrived. |

4. Trigger ViewContent: load the page, wait 15+ seconds, scroll to
   services. Confirm **one** ViewContent in Test events. Reload and repeat
   — it must **not** fire again in the same session.
5. **Clear `META_TEST_EVENT_CODE`.** Events carrying a test code do not
   count toward optimization.

### Also test

Declined consent (no Meta events at all, but the lead still saves and
emails); duplicate submit; validation failure; thank-you refresh; slow
connection; Android; iOS.

## Reading `capi_status`

| Value | Meaning |
|---|---|
| `sent` | Meta accepted the event. |
| `skipped_consent` | Visitor declined cookies. Correct behaviour, not a fault. |
| `skipped_config` | No token configured. Step 2 is incomplete. |
| `failed` | Meta rejected it, or the network failed. Check `capi_response` and the PHP error log. |
| `error` | Exception thrown. Check the error log. |

A useful weekly reconciliation, per Section 21:

```sql
SELECT DATE(created_at) AS day,
       COUNT(*)                                              AS leads,
       SUM(capi_status = 'sent')                             AS capi_sent,
       SUM(consent_state = 'accepted')                       AS consented,
       SUM(meta_ad_id IS NOT NULL AND meta_ad_id <> '')      AS attributed_to_ad
FROM leads
WHERE created_at >= NOW() - INTERVAL 7 DAY
GROUP BY day ORDER BY day DESC;
```

`leads` vs `capi_sent` vs Meta's own count is the reconciliation the
weekly scorecard asks for. Persistent gaps mean consent decline rates or
a token problem — not, any longer, a race condition.

## Ad URL parameters

Set this as the destination URL suffix on every ad so the CRM can compare
ads and placements rather than just campaigns:

```
utm_source={{site_source_name}}&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_content={{ad.name}}&utm_term={{adset.name}}&meta_campaign_id={{campaign.id}}&meta_adset_id={{adset.id}}&meta_ad_id={{ad.id}}&placement={{placement}}
```

These are captured on arrival and held in `sessionStorage`, so a visitor
who lands from an ad, browses, and submits later keeps their attribution.
First touch in the session wins.

## Files

| File | Role |
|---|---|
| `migrations/2026-08-09-tracking.sql` | Adds 16 columns + 4 indexes. Idempotent. |
| `includes/meta-capi.php` | Conversions API client. Hashing, consent gate, 4s timeout. |
| `submit-lead.php` | Generates the dedup keys, persists attribution, calls CAPI, returns `event_id`. |
| `assets/js/main.js` | Attribution capture, ViewContent trigger, deduplicated Lead. |
| `index.html` | Hidden attribution fields. |
| `thank-you.html` | PageView only. |
| `config/config.php` | Meta constants. Server copy only — gitignored, not deployed. |
