// Builds the Insights blog from Markdown.
//
//   content/insights/<slug>.md  ->  insights/<slug>/index.html
//                                   insights/index.html (article list)
//                                   sitemap.xml (rebuilt from scratch)
//
// Each Markdown file starts with a front-matter block:
//   ---
//   title: What an Owner's Engineer does on a solar mini-grid
//   description: One or two sentences for Google and the article list.
//   date: 2026-11-09
//   category: Owner's Engineer
//   draft: false          (optional; true keeps it off the site)
//   order: 1              (optional; breaks ties between posts with the same date)
//   updated: 2026-12-01   (optional; date of a significant revision)
//   ---
//
// Run with `npm run build:insights` (also part of `npm run build`).
// The generated HTML is committed, because the deploy uploads the repo as-is.

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const crypto = require('crypto');
const { marked } = require('marked');

const ROOT = path.join(__dirname, '..');
const SRC = path.join(ROOT, 'content', 'insights');
const OUT = path.join(ROOT, 'insights');
// Service landing pages (used by Google Ads ad groups) share the same templates.
//   content/services/<slug>.md  ->  services/<slug>/index.html
// Front matter: title, description, eyebrow, facts ("Label: value | Label: value"),
// related (comma-separated Insights slugs), optional seo_title, updated.
const SRC_SERVICES = path.join(ROOT, 'content', 'services');
const OUT_SERVICES = path.join(ROOT, 'services');
const SITE = 'https://helioraconsulting.com';
const AUTHOR = 'Heliora Engineering Team';
const WPM = 220;

// ── helpers ─────────────────────────────────────────────────────────────
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const read = (p) => fs.readFileSync(path.join(ROOT, p), 'utf8');
const hash = (...files) => crypto.createHash('md5').update(files.map(read).join('')).digest('hex').slice(0, 10);
const fmtDate = (iso) => new Date(iso + 'T12:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
const today = new Date().toISOString().slice(0, 10);

function gitDate(file) {
  try { return execSync(`git log -1 --format=%cs -- "${file}"`, { cwd: ROOT }).toString().trim() || today; }
  catch { return today; }
}

function parse(file, dir = SRC, required = ['title', 'description', 'date', 'category']) {
  const raw = fs.readFileSync(path.join(dir, file), 'utf8').replace(/\r\n/g, '\n');
  const m = raw.match(/^---\n([\s\S]*?)\n---\n([\s\S]*)$/);
  if (!m) throw new Error(`${file}: missing front matter`);
  const meta = {};
  for (const line of m[1].split('\n')) {
    const kv = line.match(/^(\w+):\s*(.*)$/);
    if (kv) meta[kv[1]] = kv[2].replace(/^["']|["']$/g, '').trim();
  }
  for (const k of required) {
    if (!meta[k]) throw new Error(`${file}: front matter needs "${k}"`);
  }
  const body = m[2].trim();
  const words = body.replace(/[#>*_`|\-\[\]()]/g, ' ').split(/\s+/).filter(Boolean).length;
  return {
    ...meta,
    slug: file.replace(/\.md$/, ''),
    draft: meta.draft === 'true',
    minutes: Math.max(1, Math.round(words / WPM)),
    html: marked.parse(body),
  };
}

// Tracking tags live once, in index.html. Copy that block so blog readers are
// measured by the same GA4 / Meta / LinkedIn / X setup without a second copy
// of the IDs to keep in sync.
const tracking = (read('index.html').match(/<script>\s*\/\* ── Tracking configuration[\s\S]*?<\/script>/) || [''])[0];
if (!tracking) console.warn('! tracking block not found in index.html; blog pages will have no analytics');

const v = hash('assets/css/tailwind.css', 'assets/css/style-rebrand.css', 'assets/css/insights.css');

// ── shared page parts ───────────────────────────────────────────────────
function head({ title, description, url, type = 'website', jsonld, section = 'insights' }) {
  const navLink = (href, label, key) => section === key
    ? `<li><a href="${href}" class="nav-link active text-[13px] text-h-body transition-colors" aria-current="page">${label}</a></li>`
    : `<li><a href="${href}" class="nav-link text-[13px] text-h-mid hover:text-h-body transition-colors">${label}</a></li>`;
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>${esc(title)}</title>
  <meta name="description" content="${esc(description)}" />
  <link rel="canonical" href="${url}" />
  <meta property="og:type" content="${type}" />
  <meta property="og:site_name" content="Heliora Consulting Limited" />
  <meta property="og:title" content="${esc(title)}" />
  <meta property="og:description" content="${esc(description)}" />
  <meta property="og:url" content="${url}" />
  <meta property="og:image" content="${SITE}/assets/images/og-insights.jpg" />
  <meta name="twitter:card" content="summary_large_image" />
  <link rel="icon" href="/assets/images/logo-2026.png" />
  <link rel="preload" href="/assets/fonts/fraunces-latin.woff2" as="font" type="font/woff2" crossorigin />
  <link rel="stylesheet" href="/assets/css/tailwind.css?v=${v}" />
  <link rel="stylesheet" href="/assets/css/style-rebrand.css?v=${v}" />
  <link rel="stylesheet" href="/assets/css/insights.css?v=${v}" />
  <link rel="stylesheet" href="/assets/css/fonts.css?v=${v}" />
  <script type="application/ld+json">${JSON.stringify(jsonld)}</script>
</head>
<body class="bg-white text-h-body font-sans antialiased">
<a href="#main" class="skip-link">Skip to content</a>

<header class="ins-header sticky top-0 z-50 bg-white/95 border-b border-h-divider">
  <div class="max-w-screen-xl mx-auto px-5 lg:px-12 h-16 flex items-center justify-between gap-6">
    <a href="/" class="inline-flex items-center flex-shrink-0">
      <img src="/assets/images/heliora-lockup-300.webp?v=202611011300" width="300" height="99" alt="Heliora Consulting Limited" class="h-10 w-auto" />
    </a>
    <nav aria-label="Main" class="hidden lg:block">
      <ul class="flex items-center gap-7">
        ${navLink('/#roadmap', 'Services', 'services')}
        <li><a href="/#clients" class="nav-link text-[13px] text-h-mid hover:text-h-body transition-colors">Who We Serve</a></li>
        <li><a href="/#about" class="nav-link text-[13px] text-h-mid hover:text-h-body transition-colors">About</a></li>
        ${navLink('/insights/', 'Insights', 'insights')}
        <li><a href="/#contact" class="nav-link text-[13px] text-h-mid hover:text-h-body transition-colors">Contact</a></li>
      </ul>
    </nav>
    <a href="/#contact" class="inline-flex items-center px-4 lg:px-5 py-2.5 rounded-lg bg-h-teal hover:bg-h-tealc text-white text-[13px] font-semibold transition-colors duration-200">Discuss Your Project</a>
  </div>
</header>
`;
}

const footer = `
<footer class="bg-h-offwhite border-t border-h-divider py-10">
  <div class="max-w-screen-xl mx-auto px-5 lg:px-12 flex flex-col md:flex-row justify-between gap-6 text-[13px]">
    <div>
      <p class="text-h-body font-semibold mb-1">Heliora Consulting Limited</p>
      <p class="text-h-muted">Bassan Plaza, Central Business District, FCT Abuja, Nigeria</p>
      <a href="mailto:contact@helioraconsulting.com" class="text-h-muted hover:text-h-teal transition-colors">contact@helioraconsulting.com</a>
    </div>
    <ul class="flex flex-wrap gap-x-6 gap-y-2 md:justify-end md:items-start">
      <li><a href="/" class="text-h-muted hover:text-h-teal transition-colors">Home</a></li>
      <li><a href="/#roadmap" class="text-h-muted hover:text-h-teal transition-colors">Services</a></li>
      <li><a href="/insights/" class="text-h-muted hover:text-h-teal transition-colors">Insights</a></li>
      <li><a href="/privacy.html" class="text-h-muted hover:text-h-teal transition-colors">Privacy Policy</a></li>
    </ul>
  </div>
  <p class="max-w-screen-xl mx-auto px-5 lg:px-12 mt-6 text-h-muted text-[12px]">&copy; ${new Date().getFullYear()} Heliora Consulting Limited. All rights reserved.</p>
</footer>
`;

const end = `${tracking}
</body>
</html>
`;

function cta(heading, text) {
  return `
  <aside class="ins-cta bg-h-navy text-white rounded-3xl px-7 py-10 sm:px-12 sm:py-12" aria-label="Talk to Heliora">
    <p class="section-eyebrow text-h-orangeb">Talk to an engineer</p>
    <h2 class="text-[clamp(26px,3.4vw,36px)] leading-[1.12] text-white mb-4 max-w-[22ch]">${heading}</h2>
    <p class="text-white/75 text-[16px] leading-relaxed max-w-[52ch] mb-8">${text}</p>
    <a href="/#contact" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-lg bg-h-teal hover:bg-h-tealb text-white text-[15px] font-semibold transition-colors duration-200">
      Discuss Your Project <span aria-hidden="true">&rarr;</span>
    </a>
  </aside>`;
}

function metaLine(p) {
  return `<time datetime="${p.date}">${fmtDate(p.date)}</time><span aria-hidden="true">&middot;</span><span>${p.minutes} min read</span>`;
}

// ── article page ────────────────────────────────────────────────────────
function articlePage(p, others) {
  const url = `${SITE}/insights/${p.slug}/`;
  const jsonld = {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'BlogPosting',
        headline: p.title,
        description: p.description,
        datePublished: p.date,
        dateModified: p.updated || p.date,
        mainEntityOfPage: url,
        image: `${SITE}/assets/images/og-insights.jpg`,
        author: { '@type': 'Organization', name: AUTHOR, url: SITE },
        publisher: { '@type': 'Organization', name: 'Heliora Consulting Limited', url: SITE, logo: { '@type': 'ImageObject', url: `${SITE}/assets/images/heliora-lockup.png` } },
        articleSection: p.category,
      },
      {
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Home', item: `${SITE}/` },
          { '@type': 'ListItem', position: 2, name: 'Insights', item: `${SITE}/insights/` },
          { '@type': 'ListItem', position: 3, name: p.title, item: url },
        ],
      },
    ],
  };

  const more = others.slice(0, 3).map((o) => `
        <li>
          <a href="/insights/${o.slug}/" class="ins-more group block py-6 border-t border-h-divider">
            <span class="block text-[12px] font-semibold tracking-[0.12em] uppercase text-h-teal mb-2">${esc(o.category)}</span>
            <span class="ins-more-title block text-[20px] leading-snug text-h-body group-hover:text-h-teal transition-colors">${esc(o.title)}</span>
          </a>
        </li>`).join('');

  return head({ title: `${p.title} | Heliora Insights`, description: p.description, url, type: 'article', jsonld }) + `
<main id="main">
  <article>
    <header class="max-w-[760px] mx-auto px-5 pt-12 lg:pt-20 pb-8">
      <nav aria-label="Breadcrumb" class="text-[13px] text-h-muted mb-8">
        <a href="/insights/" class="hover:text-h-teal transition-colors">Insights</a>
        <span aria-hidden="true" class="mx-2">/</span>
        <span>${esc(p.category)}</span>
      </nav>
      <h1 class="text-[clamp(34px,5vw,52px)] leading-[1.08] text-h-body mb-6">${esc(p.title)}</h1>
      <p class="text-[19px] leading-[1.6] text-h-mid mb-8">${esc(p.description)}</p>
      <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-h-muted pb-8 border-b border-h-divider">
        <span class="text-h-body font-semibold">${AUTHOR}</span><span aria-hidden="true">&middot;</span>${metaLine(p)}
      </p>
    </header>

    <div class="prose-h max-w-[760px] mx-auto px-5 pb-16">
${p.html}
    </div>
  </article>

  <div class="max-w-[760px] mx-auto px-5 pb-16">${cta('Planning a project like this one?', 'Tell us where your project stands. A senior Heliora engineer will reply within 24 hours for a scoping call. It costs nothing and commits you to nothing.')}
  </div>
${more ? `
  <section class="max-w-[760px] mx-auto px-5 pb-20" aria-labelledby="more-heading">
    <h2 id="more-heading" class="text-[24px] text-h-body mb-2">More from Insights</h2>
    <ul>${more}
    </ul>
  </section>` : ''}
</main>
` + footer + end;
}

// ── service landing page ────────────────────────────────────────────────
function servicePage(s, posts) {
  const url = `${SITE}/services/${s.slug}/`;
  const facts = (s.facts || '').split('|').map((f) => f.split(':')).filter((f) => f.length >= 2)
    .map(([k, ...v]) => `
        <div class="ins-fact">
          <dt class="text-[12px] font-semibold tracking-[0.12em] uppercase text-h-muted mb-1">${esc(k.trim())}</dt>
          <dd class="text-[16px] font-semibold text-h-body">${esc(v.join(':').trim())}</dd>
        </div>`).join('');
  const related = (s.related || '').split(',').map((x) => x.trim()).filter(Boolean)
    .map((slug) => posts.find((p) => p.slug === slug)).filter(Boolean);
  const jsonld = {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'Service',
        name: s.title,
        description: s.description,
        serviceType: s.eyebrow,
        url,
        areaServed: [{ '@type': 'Country', name: 'Nigeria' }, { '@type': 'Place', name: 'Africa' }],
        provider: { '@type': 'Organization', name: 'Heliora Consulting Limited', url: SITE, email: 'contact@helioraconsulting.com' },
      },
      {
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Home', item: `${SITE}/` },
          { '@type': 'ListItem', position: 2, name: 'Services', item: `${SITE}/#roadmap` },
          { '@type': 'ListItem', position: 3, name: s.eyebrow, item: url },
        ],
      },
    ],
  };

  const more = related.map((o) => `
        <li>
          <a href="/insights/${o.slug}/" class="ins-more group block py-6 border-t border-h-divider">
            <span class="block text-[12px] font-semibold tracking-[0.12em] uppercase text-h-teal mb-2">${esc(o.category)}</span>
            <span class="ins-more-title block text-[20px] leading-snug text-h-body group-hover:text-h-teal transition-colors">${esc(o.title)}</span>
          </a>
        </li>`).join('');

  return head({ title: s.seo_title || `${s.title} | Heliora Consulting`, description: s.description, url, jsonld, section: 'services' }) + `
<main id="main">
  <section class="max-w-[760px] mx-auto px-5 pt-12 lg:pt-20 pb-10">
    <nav aria-label="Breadcrumb" class="text-[13px] text-h-muted mb-8">
      <a href="/#roadmap" class="hover:text-h-teal transition-colors">Services</a>
      <span aria-hidden="true" class="mx-2">/</span>
      <span>${esc(s.eyebrow)}</span>
    </nav>
    <p class="section-eyebrow text-h-teal">${esc(s.eyebrow)}</p>
    <h1 class="text-[clamp(34px,5vw,52px)] leading-[1.08] text-h-body mb-6">${esc(s.title)}</h1>
    <p class="text-[19px] leading-[1.6] text-h-mid mb-8">${esc(s.description)}</p>
    <a href="/#contact" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-lg bg-h-teal hover:bg-h-tealc text-white text-[15px] font-semibold transition-colors duration-200">
      Discuss Your Project <span aria-hidden="true">&rarr;</span>
    </a>${facts ? `
    <dl class="ins-facts grid grid-cols-2 sm:grid-cols-3 gap-px mt-10 rounded-2xl border border-h-divider bg-h-divider overflow-hidden">${facts}
    </dl>` : ''}
  </section>

  <div class="prose-h max-w-[760px] mx-auto px-5 pb-16">
${s.html}
  </div>

  <div class="max-w-[760px] mx-auto px-5 pb-16">${cta('Tell us where your project stands.', 'A senior Heliora engineer will reply within 24 hours for a scoping call. If we are not the right fit, we will say so.')}
  </div>
${more ? `
  <section class="max-w-[760px] mx-auto px-5 pb-20" aria-labelledby="more-heading">
    <h2 id="more-heading" class="text-[24px] text-h-body mb-2">Related reading</h2>
    <ul>${more}
    </ul>
  </section>` : ''}
</main>
` + footer + end;
}

// ── index page ──────────────────────────────────────────────────────────
function indexPage(posts) {
  const url = `${SITE}/insights/`;
  const description = 'Practical guidance on solar feasibility, mini-grid design, Owner’s Engineer services and C&I solar in Nigeria, from the engineers at Heliora Consulting.';
  const jsonld = {
    '@context': 'https://schema.org',
    '@type': 'Blog',
    name: 'Heliora Insights',
    url,
    description,
    publisher: { '@type': 'Organization', name: 'Heliora Consulting Limited', url: SITE },
    blogPost: posts.map((p) => ({ '@type': 'BlogPosting', headline: p.title, url: `${SITE}/insights/${p.slug}/`, datePublished: p.date })),
  };

  const items = posts.map((p, i) => `
      <li>
        <a href="/insights/${p.slug}/" class="ins-row group grid md:grid-cols-12 gap-x-10 gap-y-3 py-10 border-t border-h-divider">
          <div class="md:col-span-3 text-[13px] text-h-muted flex md:flex-col gap-x-3 gap-y-1.5">
            <span class="font-semibold tracking-[0.12em] uppercase text-[12px] text-h-teal">${esc(p.category)}</span>
            <span class="flex gap-x-3">${metaLine(p)}</span>
          </div>
          <div class="md:col-span-9">
            <h2 class="${i === 0 ? 'text-[clamp(28px,3.6vw,40px)]' : 'text-[clamp(23px,2.6vw,28px)]'} leading-[1.15] text-h-body group-hover:text-h-teal transition-colors mb-3">${esc(p.title)}</h2>
            <p class="text-[16px] leading-[1.65] text-h-mid max-w-[62ch] mb-4">${esc(p.description)}</p>
            <span class="ins-read text-[14px] font-semibold text-h-teal">Read the article <span aria-hidden="true">&rarr;</span></span>
          </div>
        </a>
      </li>`).join('');

  return head({ title: 'Insights: Solar Engineering in Nigeria | Heliora Consulting', description, url, jsonld }) + `
<main id="main">
  <section class="max-w-screen-xl mx-auto px-5 lg:px-12 pt-14 lg:pt-24 pb-10 lg:pb-14">
    <p class="section-eyebrow text-h-teal">Insights</p>
    <h1 class="text-[clamp(38px,6vw,68px)] leading-[1.04] text-h-body max-w-[16ch] mb-6">Engineering notes for solar projects that have to get built</h1>
    <p class="text-[18px] leading-[1.65] text-h-mid max-w-[58ch]">Practical guidance on feasibility, design and delivery, from the engineers who do the work. Written for developers, EPC contractors, agencies and C&amp;I owners in Nigeria.</p>
  </section>

  <section class="max-w-screen-xl mx-auto px-5 lg:px-12 pb-20" aria-label="Articles">
    <ul class="border-b border-h-divider">${items}
    </ul>
  </section>

  <div class="max-w-screen-xl mx-auto px-5 lg:px-12 pb-20">${cta('Have a project at feasibility or design stage?', 'Send us the basics. A senior engineer will tell you plainly what the project needs next, and whether we are the right firm for it.')}
  </div>
</main>
` + footer + end;
}

// ── sitemap ─────────────────────────────────────────────────────────────
function sitemap(posts, services) {
  const latest = posts.reduce((d, p) => ((p.updated || p.date) > d ? (p.updated || p.date) : d), '0000');
  const urls = [
    { loc: `${SITE}/`, lastmod: gitDate('index.html') },
    ...services.map((s) => ({ loc: `${SITE}/services/${s.slug}/`, lastmod: s.updated || gitDate(`content/services/${s.slug}.md`) })),
    { loc: `${SITE}/insights/`, lastmod: latest },
    ...posts.map((p) => ({ loc: `${SITE}/insights/${p.slug}/`, lastmod: p.updated || p.date })),
    { loc: `${SITE}/privacy.html`, lastmod: gitDate('privacy.html') },
  ];
  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls.map((u) => `  <url>\n    <loc>${u.loc}</loc>\n    <lastmod>${u.lastmod}</lastmod>\n  </url>`).join('\n')}
</urlset>
`;
}

// ── build ───────────────────────────────────────────────────────────────
const posts = fs.readdirSync(SRC).filter((f) => f.endsWith('.md')).map((f) => parse(f))
  .filter((p) => !p.draft)
  .sort((a, b) => b.date.localeCompare(a.date) || (+a.order || 99) - (+b.order || 99) || a.title.localeCompare(b.title));

// Clear old article folders so a renamed or drafted post disappears.
if (fs.existsSync(OUT)) {
  for (const d of fs.readdirSync(OUT, { withFileTypes: true })) {
    if (d.isDirectory()) fs.rmSync(path.join(OUT, d.name), { recursive: true });
  }
}
fs.mkdirSync(OUT, { recursive: true });

for (const p of posts) {
  fs.mkdirSync(path.join(OUT, p.slug), { recursive: true });
  fs.writeFileSync(path.join(OUT, p.slug, 'index.html'), articlePage(p, posts.filter((o) => o !== p)));
}
fs.writeFileSync(path.join(OUT, 'index.html'), indexPage(posts));

const services = fs.existsSync(SRC_SERVICES)
  ? fs.readdirSync(SRC_SERVICES).filter((f) => f.endsWith('.md'))
    .map((f) => parse(f, SRC_SERVICES, ['title', 'description', 'eyebrow'])).filter((s) => !s.draft)
  : [];
if (fs.existsSync(OUT_SERVICES)) fs.rmSync(OUT_SERVICES, { recursive: true });
for (const s of services) {
  fs.mkdirSync(path.join(OUT_SERVICES, s.slug), { recursive: true });
  fs.writeFileSync(path.join(OUT_SERVICES, s.slug, 'index.html'), servicePage(s, posts));
}

fs.writeFileSync(path.join(ROOT, 'sitemap.xml'), sitemap(posts, services));
console.log(`Built ${posts.length} article(s), ${services.length} service page(s), insights index + sitemap.xml`);
