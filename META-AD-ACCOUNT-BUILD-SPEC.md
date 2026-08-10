# Meta Ad Account Build Spec — H2 2026

Derived from the H2 2026 Meta Campaign Strategy **v1.2**, Sections 06 (architecture),
07 (audience) and 08 (budget). Every value below traces to the document; where the
Meta API needs a decision the document does not make, it is flagged **[BUILD CHOICE]**.

**Account:** `1700476894581797` — Heliora's Ad Account
**Business:** `1538688486264568` — Heliora Consulting Limited
**Page:** `1120580674476963` — Heliora Consulting Limited (Facebook + Instagram)
**Currency:** NGN · **Min daily budget:** NGN 1,361.51
**Dataset:** `1071599118775013` — Heliora Consulting Website (connected, active, receiving)

> **Asset connection state, confirmed in Business Settings 9 Aug 2026:**
> The dataset IS connected to the ad account. The **Page is NOT** — the ad
> account's Connected assets panel reads "You don't have any connected assets".
>
> Note the two panels are not symmetric and this misled me once: an ad
> account's *Connected assets* lists Pages and Instagram accounts, while
> datasets live under *Data Sources → Datasets & pixels*. Checking one does
> not tell you about the other. Verify both.
>
> To connect: Business Settings → Accounts → Pages → Heliora Consulting
> Limited → **Connect assets** → Heliora's Ad Account.

---

## Pre-flight gates — must ALL clear before creation

| # | Gate | Status |
|---|---|---|
| 1 | Pixel connected to ad account | **DONE** — verified via API |
| 2 | ViewContent firing on qualified engagement only | **DONE** — verified previously |
| 3 | Server-confirmed Lead via CAPI, deduped on `event_id` | **DONE** — `server_last_fired` reconciles with lead 34 to the minute |
| 4 | Page `1120580674476963` connected to the ad account | **OPEN** — confirmed absent in Business Settings, not just missing from the API |
| 5 | Lead Generation ToS accepted on the Page | **UNKNOWN** — needed for the Instant Form half of mixed conversion location. Accept at `facebook.com/legal/leadgen/tos`. The API exposes `leadgen_tos_accepted`; check before building the ad set rather than letting creation fail. |
| 6 | Meta connector reconnected with Page read access | **OPEN** — `ads_get_pages_for_business` returns a permissions error, so Page state cannot currently be verified via API |
| 7 | Exclusion audiences built | **OPEN** — see Audiences below |
| 8 | Creative assets (4 static, 3 video, 2 carousel, 2 founder) | In progress separately in Codex |

Gates 4–6 block **ads**, not campaigns or ad sets. The skeleton can be built first.

---

## Campaign structure

Two live campaigns plus an unassigned reserve. The binding constraint is
optimization-event volume, not money: Meta needs ~50 events per ad set per week to
exit learning, and Lead reaches only ~7% of that at this budget. Hence ViewContent
as the optimization event and Lead as the reported outcome.

### C01 — Website Prospecting · 78% · NGN 2,340,000

| Setting | Value |
|---|---|
| Name | `HCL_NG_LEADS_MIXED_PROS_H2_2026` |
| Objective | `OUTCOME_LEADS` |
| Conversion location | Mixed — Website + Instant Form **[BUILD CHOICE: verify enum via `ads_get_field_context` before creation]** |
| Ad sets | **Exactly one.** No service, city, or placement splits. |
| Ad set name | `NG_BROAD_ADVPLUS_SINGLE` |
| Optimization goal | `OFFSITE_CONVERSIONS` |
| Promoted object | pixel `1071599118775013`, custom event `VIEW_CONTENT` |
| Billing event | `IMPRESSIONS` |
| Bid strategy | `LOWEST_COST_WITHOUT_CAP` (highest volume) — **never** a cost cap, at any point in the flight |
| Attribution | 7-day click / 1-day view |
| Placements | Advantage+ |
| Destination | `https://helioraconsulting.com/` — never the thank-you page |
| Cadence | Always on, 126 live days |

### C02 — Retargeting · 12% · NGN 360,000

Leads → Website → Lead. **Windowed, never continuous:** 28 live days across three
bursts — ~10 days October, ~10 November, ~8 early December.

Two hard activation conditions, both required:
- Retargeting pool exceeds **2,000**
- Daily rate is **NGN 12,000 or above**

Below that rate, retargeting against a pool this size neither delivers nor teaches.

### Winner allocation · 10% · NGN 300,000

Unassigned at launch. Released at **day 60** into whichever campaign demonstrates
commercial quality, judged on **cost per MQL** — not CTR, not CPL. May be formally
withheld. Do not pre-commit it in the account.

---

## Budget pacing

Per Section 08. **Dark 21–31 December** — eleven days of holiday delivery at ~NGN
20,000/day buys little; redeployed it lifts November and early-December above the
delivery threshold.

| Month | Live days | C01 | C02 | Winner | Total | Total/day |
|---|---|---|---|---|---|---|
| 17–31 Aug | 15 | 375,000 | — | — | 375,000 | 25,000 |
| September | 30 | 720,000 | — | — | 720,000 | 24,000 |
| October | 31 | 650,000 | 130,000 | — | 780,000 | 25,161 |
| November | 30 | 400,000 | 130,000 | 160,000 | 690,000 | 23,000 |
| 1–20 Dec | 20 | 195,000 | 100,000 | 140,000 | 435,000 | 21,750 |
| **Total** | **126** | **2,340,000** | **360,000** | **300,000** | **3,000,000** | **23,810** |

### C01 daily budget by month — and an open problem

The per-day column above is the *account* total. C01's own daily rate declines as
C02 and the winner allocation take share:

| Month | C01/day | Modelled ViewContent/day | Per week | vs 50/wk threshold |
|---|---|---|---|---|
| 17–31 Aug | 25,000 | 15.8 | 110 | 220% — clear |
| September | 24,000 | 15.1 | 106 | 212% — clear |
| October | 20,967 | 13.2 | 92 | 185% — clear |
| November | 13,333 | 8.4 | 59 | 118% — thin |
| 1–20 Dec | 9,750 | 6.1 | **43** | **86% — BELOW** |

> **⚠️ This needs a decision before launch.** Scaling Section 06's own model (15
> ViewContent/day at NGN 23,810/day) down to C01's actual December rate puts it at
> roughly 43 events/week — under the 50/week learning threshold. December would
> reproduce the exact failure the v1.1 revision was written to eliminate, just via
> budget dilution across three lines instead of four.
>
> November at 59/week is thin but survivable. December is not.
>
> Three ways out, in my order of preference:
> 1. **Do not run all three lines concurrently in December.** Fold the winner
>    allocation into C01 rather than treating it as a parallel line — it was always
>    meant to reinforce a proven winner, not fragment spend further.
> 2. **Shorten December and concentrate.** Go dark 15 Dec instead of 21 Dec and
>    hold C01 above ~13,000/day for the days that remain.
> 3. **Accept it deliberately** as a wind-down period where December is for pipeline
>    capture rather than learning — but record that in the plan so the December
>    numbers are not later read as performance decay.
>
> This is an issue in the strategy document, not in the build. Worth raising with
> the MD alongside the day-60 winner decision, since they interact.

---

## Audience — one broad pool, creative does the targeting

| Layer | Setting |
|---|---|
| Location | Nigeria, nationwide. Diagnose state/city only after lead-quality data exists. |
| Age | 25–65+ **only if** it does not disable Advantage+ audience. If it does, use 18+ and revisit after a clean baseline. |
| Language | No restriction. English creative. Platform-language filters exclude qualified Nigerian decision-makers. |
| Detailed targeting | Suggestions only, not controls: solar energy, renewable energy, electrical engineering, project management, infrastructure, power generation, energy finance, sustainability |
| Lookalikes | **None at launch.** Delay until there is a clean, sufficient seed. |

**Do not** build many small interest stacks, split cities, or assume job-title
targeting is complete. Over-segmentation starves delivery and manufactures false
certainty.

### Exclusion audiences — build before launch (gate 7)

Prevents waste and stops recruitment/supplier traffic polluting the optimization
signal:

- Existing leads, 180-day window
- Current clients
- Employees
- Vendors and suppliers
- Job applicants

Customer-list audiences require permission and compliant data handling. The
180-day lead exclusion can be sourced from the `leads` table — `lead_uid` and
hashed email.

### Retargeting pool for C02

Website custom audience from pixel `1071599118775013`. Build it from launch and
**spend nothing against it** until it clears 2,000. Also build 25%, 50% and 95%
video-viewer audiences per Section 18.

---

## Naming convention

```
Campaign:      HCL_NG_LEADS_MIXED_PROS_H2_2026
Ad set:        NG_BROAD_ADVPLUS_SINGLE
Ad:            MINIGRID_STATIC_PROJECTSTALL_V1
Creative tag:  SERVICE_FORMAT_HOOK_VERSION
```

**Ad-level tagging discipline is now load-bearing.** Because the structure no longer
separates services into ad sets, the ad name is the *only* carrier of the service
angle into CRM reporting. A sloppy ad name means a service comparison that cannot be
reconstructed later. The `utm_content` field already flows through to
`UTM_Content` in Zoho, so the ad name and the UTM should agree exactly.

---

## Phased optimization

| Phase | Window | Target | Exit gate |
|---|---|---|---|
| 1. Calibrate | 17–31 Aug | ViewContent | One test lead reconciles across Meta, GA4 and CRM. CPM and CTR within ±30% of model. Re-run the funnel model on live inputs before September budget releases. |
| 2. Learn | Sep–Oct | ViewContent | 8–12 leads with MQL labels. One hook and one format clearly ahead on **cost per MQL, not CTR**. |
| 3. Qualify | November | ViewContent (Lead targeted) | Cost per MQL inside working range. Winner released or formally withheld. |
| 4. Concentrate | 1–20 Dec | Unchanged | H2 playbook documented, mature-cohort report into Q1 2027, 2027 budget case. |

**Do not switch the optimization event on thin evidence.** Every change restarts
learning and costs roughly a week of stable delivery. Move ViewContent → Lead only
after three consecutive weeks at 25+ leads/week — a level this budget is unlikely to
reach. Plan on ViewContent for the whole flight; treat a switch as upside, not a
milestone.

---

## Execution order

1. Create C01 campaign (`PAUSED`), no Page needed.
2. Create the single ad set with ViewContent promoted object (`PAUSED`).
3. Build exclusion audiences and the website custom audience.
4. Link the Page, accept Lead Gen ToS, reconnect the connector.
5. Add creatives as Codex delivers them; verify each with `ads_get_ad_preview`.
6. Resolve the December budget question above.
7. Re-verify the tracking gate, then activate on 17 August.

Everything is created **paused**. Nothing spends until you activate it explicitly.
