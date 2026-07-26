# Product

## Register

brand

## Users

Decision-makers and technical leads at organisations that build, fund, or oversee solar projects in Nigeria and West Africa:

- **MDAs & government bodies** — Rural Electrification Agency (REA), NERC, federal/state ministries, and public institutions running electrification programmes (NEP and related).
- **EPC contractors** — solar EPC firms needing independent technical validation, QA/QC, or specialist sub-consulting on contracts they've won or are bidding.
- **Project developers & IPPs** — from early feasibility to financial close, needing rigorous technical advisory to de-risk projects and satisfy investors.
- **International development agencies** — World Bank, AfDB, USAID, GIZ, Power Africa, FCDO implementing energy-access programmes.
- **C&I clients** — manufacturers, telecoms, hospitality, agro-processing needing correctly engineered solar systems.

Their context: evaluating whether Heliora is a credible, senior-led engineering partner for a specific, often high-stakes project. Many arrive skeptical (burned by generalists or slow/expensive international firms) and need proof of technical depth and regulatory fluency before they'll start a conversation. A meaningful share browse on mobile over variable Nigerian bandwidth.

## Product Purpose

A lead-generation landing page for Heliora Consulting Limited — Nigeria's specialist solar-engineering consultancy (Owner's Engineer services, feasibility studies, mini-grid design, ESIA, technical advisory). It exists to convert qualified organisations into free-consultation inquiries by first proving specialist credibility, then making the ask.

Success = a steady stream of *qualified* consultation requests from real, funded projects (MDAs, EPCs, developers, agencies) — not raw volume. The site must do the trust-building work up front so the sales conversation starts warm.

Runs on Namecheap shared hosting (HTML + Tailwind CDN + vanilla JS + PHP + MySQL), with lead capture, auto-respond email, and Zoho CRM sync — deliberately zero recurring platform fees.

## Brand Personality

**Confident but approachable.** Expert and credible without being intimidating. Heliora knows Nigeria's solar sector cold — the engineering, the software, the NERC/REA/FMEnv regulatory maze — and says so plainly, in a human voice a smaller developer or a C&I plant manager can talk to.

- Voice: precise, plain-spoken, quietly authoritative. States what it delivers concretely (single-line diagrams, HOMER Pro/PVSyst, BEME, NPV/IRR) rather than in adjectives.
- Tone: honest and direct — "we tell you clearly when an engagement doesn't fit." Trust earned, not claimed.
- Three words: **Credible. Specialist. Grounded.**
- Emotional goal: a visitor should feel *relief* ("finally, people who actually do this") and *confidence* ("this firm will get my project approved, funded, and delivered").

## Anti-references

- **Generic solar installer.** Not a residential panel company or hardware reseller. Heliora is engineering and advisory — no product-catalogue energy, no "get a quote for panels."
- **Cheap / templated.** Nothing that reads as a low-budget contractor site, clip-art, or an off-the-shelf template. Every detail must signal senior-level rigor and match the quality of the deliverables described.
- **Corporate & cold.** Avoid faceless big-firm blandness — stock boardrooms, empty jargon, no point of view. Warmth and a clear stance matter.
- **Loud / salesy.** No hype, countdown-timer urgency, or infomercial energy. The audience (government, lenders, senior engineers) distrusts it.

## Design Principles

1. **Earn trust before asking.** Lead with proof — specialist depth, named tools, regulatory fluency, concrete deliverables — and let the conversion ask feel like the obvious next step, not a hard sell.
2. **Show the engineering, don't claim it.** Specificity is the credibility engine. Name the software, the standards (NERC, REA, FMEnv, IFC), the artifacts (SLDs, BEME, protection settings). Concrete detail beats adjectives everywhere.
3. **Precision as aesthetic.** The visual craft is itself an argument: clean, exact, well-aligned, considered. A firm this careful with a landing page is careful with a mini-grid design.
4. **Grounded in Nigeria.** Local pricing, local regulation, local site realities — a deliberate contrast to slow, expensive international firms. This is the wedge; keep it visible.
5. **Approachable authority.** Confident and plain-spoken, never intimidating or cold. A serious developer and a curious C&I client should both feel invited to talk.

## Accessibility & Inclusion

Target **WCAG 2.1 AA**:

- Body text ≥ 4.5:1 contrast, large text ≥ 3:1. Audit the muted grays (`h-muted #86868b`, `h-mid #515154`) against white/off-white backgrounds and any text over the hero image; bump toward the ink end where marginal.
- Full keyboard navigation with visible focus states, including the consultation modal and mobile menu.
- `prefers-reduced-motion` alternative for every scroll-reveal, fade-up, counter, and hover animation.
- Semantic structure, meaningful `alt` text, and labelled form fields for screen readers.
- Performance is an accessibility concern here: keep payloads light and first paint fast for variable Nigerian mobile bandwidth.
