---
name: Heliora Consulting Limited
description: Solar Engineering. Precisely Delivered. — a white-based, precision-engineered brand system for Nigeria's specialist solar consultancy.
colors:
  orange: "#f47c20"
  orange-light: "#fff3e8"
  navy: "#1b2e6e"
  navy-mid: "#2a4494"
  navy-light: "#eef1f9"
  ink: "#1d1d1f"
  slate: "#515154"
  muted: "#86868b"
  placeholder: "#aeaeb2"
  border: "#d2d2d7"
  divider: "#e8e8ed"
  offwhite: "#f5f5f7"
  subtle: "#fbfbfd"
  white: "#ffffff"
  success: "#15803d"
  error: "#ef4444"
typography:
  display:
    fontFamily: "-apple-system, BlinkMacSystemFont, Inter, system-ui, sans-serif"
    fontSize: "clamp(40px, 6vw, 80px)"
    fontWeight: 900
    lineHeight: 1.0
    letterSpacing: "-0.03em"
  headline:
    fontFamily: "-apple-system, BlinkMacSystemFont, Inter, system-ui, sans-serif"
    fontSize: "clamp(34px, 5vw, 54px)"
    fontWeight: 900
    lineHeight: 1.04
    letterSpacing: "-0.04em"
  title:
    fontFamily: "-apple-system, BlinkMacSystemFont, Inter, system-ui, sans-serif"
    fontSize: "22px"
    fontWeight: 800
    lineHeight: 1.18
    letterSpacing: "-0.025em"
  body:
    fontFamily: "-apple-system, BlinkMacSystemFont, Inter, system-ui, sans-serif"
    fontSize: "17px"
    fontWeight: 400
    lineHeight: 1.65
    letterSpacing: "normal"
  label:
    fontFamily: "-apple-system, BlinkMacSystemFont, Inter, system-ui, sans-serif"
    fontSize: "11px"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "0.18em"
rounded:
  sm: "12px"
  md: "16px"
  lg: "24px"
  full: "100px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "18px"
  lg: "32px"
components:
  button-primary:
    backgroundColor: "{colors.orange}"
    textColor: "{colors.white}"
    rounded: "{rounded.full}"
    padding: "16px 32px"
  button-primary-hover:
    backgroundColor: "#fb923c"
    textColor: "{colors.white}"
  button-inverse:
    backgroundColor: "{colors.white}"
    textColor: "{colors.orange}"
    rounded: "{rounded.full}"
    padding: "16px 36px"
  card:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "32px"
  card-featured-navy:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.white}"
    rounded: "{rounded.lg}"
    padding: "40px"
  tag:
    backgroundColor: "{colors.white}"
    textColor: "{colors.slate}"
    rounded: "{rounded.full}"
    padding: "4px 12px"
  input:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "13px 15px"
---

# Design System: Heliora Consulting Limited

## 1. Overview

**Creative North Star: "The Bankable Blueprint"**

Heliora's interface is built like a document that has to pass a lender's desk. Everything sits on clean white, set in a single disciplined sans, aligned to the millimetre — the calm, structured confidence of engineering work that survives scrutiny. The design is itself the argument: a firm this exact with a landing page is exact with a mini-grid design. Orange is the one live current running through the blueprint — the single mark of solar energy on an otherwise institutional, black-ink-on-white surface. Navy carries the heavier institutional weight where a section needs gravity.

This is a **brand** surface: for Heliora, the design *is* the product. It courts government agencies (REA, NERC), EPC contractors, funded developers, and development banks — audiences that distrust anything that reads as hype. So the system is confident but never loud, warm but never casual, precise without being cold. It states what it delivers in concrete terms (single-line diagrams, HOMER Pro, BEME, NPV/IRR) and lets specificity do the persuading.

It explicitly rejects four things, drawn straight from the brand's anti-references: the look of a **generic residential solar installer** (no product-catalogue energy, no "quote for panels"); anything **cheap or templated** (no clip-art, no off-the-shelf builder feel); **corporate coldness** (no faceless stock boardrooms, no empty jargon); and **loud, salesy** urgency (no countdown timers, no infomercial hype).

**Key Characteristics:**
- White-based, black-ink-first; color used as signal, not decoration
- One typeface (system/Inter), full weight range from 300 to 900 doing all the hierarchy work
- Large, tightly-tracked black display type against generous white space
- Soft, layered depth with hairline borders; rounded but not playful geometry
- Precision, alignment, and concrete specificity as the core aesthetic argument

## 2. Colors

A near-monochrome white-and-ink base, punctuated by a single solar orange and grounded by an institutional navy. Color is rationed: the more restrained the field, the louder the orange reads.

### Primary
- **Solar Signal Orange** (#f47c20): The one voice of energy. Reserved for primary CTAs, the single emphasized phrase in a heading ("*Approved, Funded,*"), key iconography, focus rings, link hovers, and full-bleed CTA bands used as deliberate punctuation between calm sections. On white it carries a soft glow shadow (`rgba(244,124,32,.28)`).
- **Orange Light** (#fff3e8): Tint fill behind orange icons and inside soft orange callout cards. Never for text.

### Secondary
- **Institutional Navy** (#1b2e6e): The gravity color. Carries the flagship/featured cards, the darkest CTA band, and the VSL frame — moments that need weight and authority. Paired with white text and orange sub-accents.
- **Navy Mid** (#2a4494) / **Navy Light** (#eef1f9): The mid tone for gradients and hovers; the light tint for navy-keyed icon chips and soft cards.

### Neutral
- **Ink** (#1d1d1f): Primary text and display headings. Near-black, never pure #000.
- **Slate** (#515154): Sub-headings, body lead paragraphs, secondary nav text. The darkest of the grays — safe for body at AA.
- **Muted** (#86868b): Small supporting text, card descriptions, captions. **Audit this against white; it is the marginal one for 4.5:1.**
- **Placeholder** (#aeaeb2): Input placeholder text only — decorative, never real content.
- **Border** (#d2d2d7) / **Divider** (#e8e8ed): Hairline strokes. Border for inputs and stronger separations; divider for the default 1px card and section lines.
- **Off-white** (#f5f5f7) / **Subtle** (#fbfbfd): Alternating section backgrounds that separate white bands without introducing a new hue.

### Status
- **Success** (#15803d on #f0fdf4) and **Error** (#ef4444): Reserved strictly for form validation and toasts. Not part of the marketing palette.

### Named Rules
**The One Current Rule.** Orange is the *only* saturated accent on any calm section — treat it as a live wire, not a paint. One emphasized phrase per heading, one primary CTA per view. When a whole section goes orange (a CTA band), that is punctuation, not the norm; the next section returns to white.

**The Near-Black Rule.** Text ink is #1d1d1f, never pure black. Pure #000 on white reads harsh and cheap — the exact "templated" feel the brand rejects.

## 3. Typography

**Display / Body / Label Font:** System stack — `-apple-system, BlinkMacSystemFont, Inter, system-ui, sans-serif`. One family, no pairing.

**Character:** A single neutral, high-legibility sans carries the entire system. All contrast comes from *weight* (300–900) and *scale*, not from mixing typefaces. This is deliberate: a second display face would read as decoration, and this brand argues through restraint and precision, not flourish. Black (900) weight at large sizes with tight negative tracking gives the headlines their engineered, condensed authority.

### Hierarchy
- **Display** (900, clamp(40px → 80px), line-height 1.0, letter-spacing -0.03em): Hero headline only. Ink or navy, with one orange phrase.
- **Headline** (900, clamp(34px → 54px), line-height 1.04, letter-spacing -0.04em): Section headings. Often 2–3 hard-wrapped lines.
- **Title** (800, 22px, line-height 1.18, letter-spacing -0.025em): Card and service titles.
- **Body** (400, 15–17px, line-height 1.65): Paragraph copy. Lead paragraphs at 17px capped ~560px wide; card descriptions at 14–15px. Keep measure at 65–75ch.
- **Label** (700, 10–11px, letter-spacing 0.16–0.18em, UPPERCASE): Section eyebrows, card kickers, footer headings, stat captions.

### Named Rules
**The Weight-Not-Face Rule.** Hierarchy is built from weight and size within one family. Never introduce a second typeface for "elegance" — the restraint is the brand.

**The Tight-Display Rule.** Display and headline type is tracked negative (-0.03 to -0.04em) and never looser. Floor is -0.04em; tighter than that and letters touch.

## 4. Elevation

Soft, layered depth on a flat white ground. Shadows are diffuse and low-contrast — depth is *suggested*, never dramatized. Two heavier resting shadows anchor the featured moments (navy flagship cards, the VSL frame); lighter surface cards sit nearly flat with a hairline divider and gain a gentle shadow plus a −2 to −3px lift on hover. Nothing casts a hard or dark shadow; the palette of depth is subtle glow and blur, in keeping with the precise, unshouty character.

### Shadow Vocabulary
- **Card hover** (`box-shadow: 0 16px 48px rgba(0,0,0,.07)`): The default lift for service/client cards on hover, paired with `translateY(-3px)`.
- **Process hover** (`box-shadow: 0 12px 40px rgba(0,0,0,.07)`): Slightly tighter lift for process cards.
- **Navy anchor** (`box-shadow: 0 24px 60px rgba(27,46,110,.22), 0 8px 24px rgba(0,0,0,.12)`): Resting elevation for featured navy cards — the deepest shadow in the system.
- **Orange glow** (`box-shadow: 0 4px 24px rgba(244,124,32,.28), 0 2px 8px rgba(244,124,32,.12)`): Coloured halo under primary orange CTAs, so the button reads as energised.
- **Navbar float** (`box-shadow: 0 1px 0 rgba(0,0,0,.06), 0 4px 24px rgba(0,0,0,.04)`): Appears only when the nav gains its `.scrolled` glass background.

### Named Rules
**The Soft-Depth Rule.** Every shadow is large-radius and low-alpha (≤0.07 black on light surfaces). If a shadow looks like a hard drop or a 2014-era card, the blur is too small and the alpha is too high — dissolve it.

**The Coloured-Glow-For-Action Rule.** Only interactive orange (and navy featured surfaces) may cast a *coloured* shadow. Neutral cards cast neutral shadows. Colour in a shadow signals "this is alive / clickable."

## 5. Components

### Buttons
- **Shape:** Full pill (`border-radius: 100px`).
- **Primary:** Solar Signal Orange fill, white text, weight 600–700, padding `16px 32px`, with the orange glow shadow. Hover: lighten toward `#fb923c` / `#fca55e` and `scale(1.05)`.
- **Inverse (on orange/navy bands):** White fill, orange text, same pill; hover shifts to off-white. Used when the surrounding band is already orange.
- **Ghost (on imagery/dark):** Transparent white-10% fill, `1px` white-25% border, white text. Used for the secondary "Watch How We Work" action beside a primary CTA.
- **Hover / Focus:** 200ms transitions; primary buttons scale up subtly. All interactive elements must show a visible `:focus-visible` ring (reuse the orange focus glow, `0 0 0 3px rgba(244,124,32,.1)`).

### Chips / Tags
- **Style:** White fill, 1px divider border (#e8e8ed), slate text, `100px` radius, `4px 12px`, 11px weight 500. Used for capability tags and "who we serve" labels.
- **State:** Hover shifts border and text to orange (`rgba(244,124,32,.35)` / #f47c20). Association pills (#86868b, weight 600, wider tracking) are the same shape, one notch quieter.

### Cards / Containers
- **Corner Style:** `24px` (lg) for primary cards; `16px` for inner tiles; `18px` for compact goal cards.
- **Background:** White by default; off-white / tinted (orange-light, navy-light, green-50) for soft-keyed variants; solid navy for the flagship featured card.
- **Shadow Strategy:** Flat with hairline divider at rest; hover lift + soft shadow (see Elevation). Navy featured cards carry a resting anchor shadow.
- **Border:** 1px divider (#e8e8ed) default, deepening to border (#d2d2d7) on hover. **No coloured side-stripe borders — full hairline borders only.**
- **Internal Padding:** 28–32px on standard cards; 40–56px on the flagship navy card.

### Inputs / Fields
- **Style:** White fill, 1px border (#d2d2d7), `12px` radius, `13px 15px` padding, 15px ink text, placeholder #aeaeb2.
- **Focus:** Border shifts to orange with a 3px orange glow ring (`0 0 0 3px rgba(244,124,32,.1)`). Hover deepens border to #aeaeb2.
- **Error:** Red border (#ef4444) + red glow ring. Labels are 12px weight 600 slate, above the field.

### Navigation
- **Style:** Fixed, transparent over the hero; on scroll gains a white-92% glass background (`backdrop-filter: blur(20px) saturate(180%)`) and the navbar float shadow. 60px tall.
- **Links:** 13px slate text; hover reveals a 1px orange underline that grows left-to-right (`.nav-link::after`, 220ms). Active state keeps the underline full-width.
- **CTA:** "Free Consultation" primary orange pill, right-aligned.
- **Mobile:** Two-bar hamburger animating into an X; menu drops as a white-97% glass panel with a divider top border.

### Toast (signature)
Fixed bottom-right, glass background (blur 20px), 14px radius, slides up 70px with a `cubic-bezier(.16,1,.3,1)` ease. Success = green-tinted; error = red-tinted. The one piece of transient feedback in the system.

## 6. Do's and Don'ts

### Do:
- **Do** keep white (and off-white #f5f5f7) as the dominant surface; alternate white / off-white to separate bands without adding a hue.
- **Do** ration orange to one current per view — primary CTA, one emphasized heading phrase, key icons, focus rings — per **The One Current Rule**.
- **Do** build hierarchy from weight (300–900) and scale within the single system/Inter family.
- **Do** use near-black #1d1d1f for text, never pure #000.
- **Do** keep display type tracked tight (-0.03 to -0.04em) and body measure at 65–75ch.
- **Do** use full 1px hairline borders (#e8e8ed → #d2d2d7 on hover) on cards.
- **Do** keep shadows large-radius, low-alpha, and reserve coloured shadows for interactive orange / featured navy.
- **Do** give every animation — `fade-up`, `.reveal`, hover lifts, counters — a `prefers-reduced-motion: reduce` fallback (crossfade or instant). *This is currently missing in the CSS and must be added to hit the AA target in PRODUCT.md.*
- **Do** prove claims with concrete specifics (HOMER Pro, PVSyst, BEME, NPV/IRR, NERC/REA/FMEnv) — specificity is the credibility engine.

### Don't:
- **Don't** let the page read like a **generic residential solar installer** — no product-catalogue grids, no "get a quote for panels," no hardware-reseller energy.
- **Don't** ship anything that feels **cheap or templated** — no clip-art, no builder-default gradients, no pure-black harsh text, no off-the-shelf card look.
- **Don't** go **corporate and cold** — no faceless stock boardrooms, no empty jargon, no point-of-view-free filler.
- **Don't** go **loud or salesy** — no countdown timers, no urgency banners, no infomercial hype. This audience distrusts it.
- **Don't** use `border-left`/`border-right` greater than 1px as a coloured accent stripe on cards or callouts. Full borders, tints, or leading numbers instead.
- **Don't** use gradient text (`background-clip: text`); emphasis comes from a single solid orange word, weight, or size.
- **Don't** introduce a second typeface for "elegance," and never track display type looser than -0.04em.
- **Don't** cast hard, dark, or small-blur shadows; if it looks like a 2014 card, the alpha is too high and the blur too small.
