# Heliora Consulting Limited — Landing Page

Marketing landing page for Nigeria's specialist solar-engineering consultancy. Lead generation is the goal. Stack: HTML + Tailwind (CDN) + vanilla JS + PHP + MySQL on Namecheap shared hosting — no Node/VPS, no recurring platform fees.

## Design Context

Two root files define the brand and visual system. **Read them before design or UI work.**

- **[PRODUCT.md](PRODUCT.md)** — strategy: register (`brand`), users, purpose, brand personality (*Confident but approachable — Credible. Specialist. Grounded.*), anti-references, design principles, WCAG AA target.
- **[DESIGN.md](DESIGN.md)** — visual system: the "Bankable Blueprint" north star, color tokens (white base · Solar Signal Orange `#f47c20` · Institutional Navy `#1b2e6e`), Inter/system type scale, elevation, components, and do's/don'ts. Machine-readable tokens live in its YAML frontmatter; `.impeccable/design.json` carries component snippets and tonal ramps.

**In short:** clean white base, near-black text (`#1d1d1f`, never pure black), orange as a rationed accent (one CTA / one emphasized word per view), one typeface with hierarchy from weight. Avoid: generic solar-installer looks, cheap/templated feel, corporate coldness, loud/salesy urgency.

## Impeccable

This project is set up for the `/impeccable` design skill. Useful next passes: `/impeccable audit index.html` (WCAG AA — check `#86868b` muted text contrast and hero text over imagery; add `prefers-reduced-motion` fallbacks) and `/impeccable live` for in-browser variant iteration.
