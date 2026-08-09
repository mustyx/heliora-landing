/* ════════════════════════════════════════════════════════
   Heliora Consulting Limited — Main JavaScript
   ════════════════════════════════════════════════════════ */
'use strict';

/* ── Attribution capture ─────────────────────────────────────
   Captures the full Meta parameter set, not just source/medium/campaign,
   so the CRM can compare ads and placements rather than just campaigns.
   Values are persisted for the session: a visitor who arrives from an ad,
   browses, and submits later would otherwise lose every parameter.       */
const HELIORA_ATTR_KEYS = [
  'utm_source','utm_medium','utm_campaign','utm_content','utm_term',
  'meta_campaign_id','meta_adset_id','meta_ad_id','placement',
  'site_source_name','fbclid','gclid','li_fat_id'
];

function readCookie(name) {
  const m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
  return m ? decodeURIComponent(m.pop()) : '';
}

(function captureAttribution() {
  const p = new URLSearchParams(window.location.search);
  let stored = {};
  try { stored = JSON.parse(sessionStorage.getItem('heliora_attr') || '{}'); } catch { stored = {}; }

  // First touch in this session wins, so a mid-session internal navigation
  // cannot blank out the parameters the visitor actually arrived on.
  HELIORA_ATTR_KEYS.forEach(k => {
    const fromUrl = p.get(k);
    if (fromUrl) stored[k] = fromUrl;
  });
  try { sessionStorage.setItem('heliora_attr', JSON.stringify(stored)); } catch {}

  const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
  HELIORA_ATTR_KEYS.forEach(k => set('f_' + k, stored[k]));
  set('f_page_url', window.location.href);
})();

/* ── Paid-traffic qualification gate ─────────────────────────
   Section 03 of the H2 2026 strategy asks for Organisation, Client Type,
   Project Scale, Project Stage and Decision Horizon to be required "for
   paid traffic" — deliberately not for everyone. The trade is explicit in
   the document: some raw conversion rate for much stronger qualification.
   Paid clicks cost money and must be qualified; an organic visitor who
   found us through search or a referral is cheap to receive and worth
   capturing on the lightest possible form.

   Paid is decided from the session's persisted attribution, not the
   current URL, so a visitor who lands on an ad, reads three sections and
   then opens the modal is still treated as paid traffic.               */
const HELIORA_PAID_MARKERS = [
  'fbclid', 'gclid', 'li_fat_id', 'meta_ad_id', 'meta_adset_id', 'meta_campaign_id'
];
const HELIORA_PAID_MEDIUMS = ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'cpm', 'display'];

function isPaidSession() {
  let attr = {};
  try { attr = JSON.parse(sessionStorage.getItem('heliora_attr') || '{}'); } catch { attr = {}; }

  // A click identifier or an ad-level id is unambiguous.
  if (HELIORA_PAID_MARKERS.some(k => attr[k])) return true;

  // Otherwise fall back to utm_medium. Checked against a list rather than
  // just 'cpc' because the account will not be the only thing writing
  // these — agencies, boosted posts and email tools all differ.
  const medium = String(attr.utm_medium || '').toLowerCase().replace(/[^a-z_]/g, '');
  if (HELIORA_PAID_MEDIUMS.includes(medium)) return true;

  return false;
}

/* Mark the qualification fields required and reveal their asterisks. Runs
   once on load. The fields exist in the DOM either way — hiding them for
   organic visitors would mean two different forms to maintain and would
   throw away qualification data that organic visitors are often happy to
   give. They stay optional instead.                                     */
(function markPaidTrafficRequirements() {
  if (!isPaidSession()) return;

  document.querySelectorAll('[data-paid-required]').forEach(el => {
    el.setAttribute('required', 'required');
    el.setAttribute('aria-required', 'true');
  });
  document.querySelectorAll('.lf-req').forEach(el => el.classList.remove('hidden'));

  const flag = document.getElementById('f_qualification_required');
  if (flag) flag.value = '1';
})();

/* Refresh the values that can only be known at submit time. _fbp and _fbc
   are written by the Pixel after consent, so they may not exist when the
   page first loads. Consent state travels with the lead so the server can
   decide whether forwarding it to Meta is permitted at all.              */
function refreshSubmitTimeFields() {
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
  set('f_fbp', readCookie('_fbp'));
  set('f_fbc', readCookie('_fbc'));
  let consent = 'unset';
  try { consent = localStorage.getItem('heliora_consent') || 'unset'; } catch {}
  set('f_consent_state', consent);
}

/* ── ViewContent: the optimization event ─────────────────────
   Section 06 of the H2 2026 strategy optimises delivery on ViewContent
   rather than Lead, because Lead reaches only ~7% of Meta's learning
   threshold at this budget. For that to work the event must represent
   real qualified engagement - if it fired on every page load it would
   just be a landing-page view under another name and would train the
   algorithm toward the same weak traffic.

   Fires ONCE per session, on whichever comes first:
     - the visitor opens the consultation modal, or
     - the visitor reaches the services section AND has been engaged for
       at least 15 seconds.                                              */
(function viewContentTrigger() {
  const KEY = 'heliora_vc_fired';
  const landedAt = Date.now();

  function alreadyFired() {
    try { return sessionStorage.getItem(KEY) === '1'; } catch { return false; }
  }
  function markFired() {
    try { sessionStorage.setItem(KEY, '1'); } catch {}
  }

  window.heliora_fireViewContent = function (reason) {
    if (alreadyFired() || typeof fbq === 'undefined') return;
    markFired();
    fbq('track', 'ViewContent', {
      content_name: 'Qualified engagement',
      content_category: reason
    });
    if (typeof gtag !== 'undefined') {
      gtag('event', 'qualified_engagement', { event_category: 'Lead', event_label: reason });
    }
  };

  const services = document.getElementById('roadmap');
  if (services && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        if (Date.now() - landedAt < 15000) return;   // too fast to be real interest
        window.heliora_fireViewContent('services_section');
        io.disconnect();
      });
    }, { threshold: 0.4 });
    io.observe(services);
  }
})();

/* ── Navbar scroll behaviour ─────────────────────────────── */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 40);
}, { passive: true });

/* ── Mobile menu ─────────────────────────────────────────── */
const menuBtn    = document.getElementById('menu-btn');
const mobileMenu = document.getElementById('mobile-menu');

function setMenu(open) {
  mobileMenu.classList.toggle('open', open);
  menuBtn.classList.toggle('open', open);
  menuBtn.setAttribute('aria-expanded', String(open));
}

menuBtn.addEventListener('click', () => {
  setMenu(!mobileMenu.classList.contains('open'));
});

mobileMenu.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', () => setMenu(false));
});

// Close on Escape, and when tapping outside the menu
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && mobileMenu.classList.contains('open')) setMenu(false);
});
document.addEventListener('click', e => {
  if (!mobileMenu.classList.contains('open')) return;
  if (!mobileMenu.contains(e.target) && !menuBtn.contains(e.target)) setMenu(false);
});

/* ── Active nav link ─────────────────────────────────────── */
const navLinks = document.querySelectorAll('.nav-link');
const navActiveObs = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      navLinks.forEach(link => {
        link.classList.toggle('active', link.getAttribute('href') === '#' + entry.target.id);
      });
    }
  });
}, { rootMargin: '-50% 0px -50% 0px' });
document.querySelectorAll('section[id]').forEach(s => navActiveObs.observe(s));

/* ── Scroll reveal ───────────────────────────────────────── */
const revealObs = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    const delay = parseFloat(entry.target.style.animationDelay) * 1000 || 0;
    setTimeout(() => entry.target.classList.add('visible'), delay);
    revealObs.unobserve(entry.target);
  });
}, { threshold: 0.1 });

document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));

/* ── Smooth scroll with navbar offset ───────────────────── */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    if (this.classList.contains('open-consultation-modal')) return;
    const target = document.querySelector(this.getAttribute('href'));
    if (!target) return;
    e.preventDefault();
    window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 76, behavior: 'smooth' });
  });
});

/* ── Toast notification ──────────────────────────────────── */
function showToast(message, type = 'success') {
  const toast = document.getElementById('toast');
  toast.className = type;
  toast.innerHTML = `
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0">
      ${type === 'success'
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>'}
    </svg>
    <span>${message}</span>`;
  requestAnimationFrame(() => toast.classList.add('show'));
  setTimeout(() => toast.classList.remove('show'), 5500);
}

/* ── Lead form (AJAX) ────────────────────────────────────── */
const leadForm   = document.getElementById('lead-form');
const submitBtn  = document.getElementById('submit-btn');
const btnText    = document.getElementById('btn-text');
const btnArrow   = document.getElementById('btn-arrow');
const btnSpinner = document.getElementById('btn-spinner');
const formError  = document.getElementById('form-error');
const formSuccess = document.getElementById('form-success');

if (leadForm) {
  leadForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Honeypot check
    if (leadForm.querySelector('[name="website"]').value) return;

    // Required field validation. On the paid path this now covers six more
    // fields than it used to, so point the visitor at the first one they
    // missed rather than making them hunt a longer form for the red border.
    let firstInvalid = null;
    leadForm.querySelectorAll('[required]').forEach(f => {
      f.classList.remove('error');
      if (!f.value.trim()) {
        f.classList.add('error');
        if (!firstInvalid) firstInvalid = f;
      }
    });
    if (firstInvalid) {
      showError('Please complete all required fields.');
      firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
      firstInvalid.focus({ preventScroll: true });
      return;
    }

    // Email validation
    const emailEl = leadForm.querySelector('[name="email"]');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
      emailEl.classList.add('error');
      showError('Please enter a valid email address.');
      return;
    }

    setLoading(true);
    hideError();

    // _fbp / _fbc and the consent choice can change after page load, so read
    // them now rather than trusting what was captured on arrival.
    refreshSubmitTimeFields();

    let redirecting = false;

    try {
      const res  = await fetch('submit-lead.php', {
        method: 'POST',
        body:   new FormData(leadForm),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();

      if (data.success) {
        const T = window.HELIORA_TRACKING || {};
        const service = new FormData(leadForm).get('service');

        /* ── One conversion, one id, one time ────────────────────────
           The server already sent this Lead to Meta via the Conversions
           API using data.event_id. Firing the browser event with the SAME
           id lets Meta deduplicate and keep the richer of the two. Never
           generate the id here - two different ids means two conversions
           counted for one lead.

           transport_type 'beacon' matters: this page navigates to
           thank-you.html immediately afterwards, and a normal XHR would
           frequently be cancelled mid-flight. That cancellation is why
           the old implementation under-reported.                        */
        if (typeof gtag !== 'undefined') {
          gtag('event', 'generate_lead', {
            event_category: 'Lead',
            event_label: service,
            value: 1,
            transaction_id: data.lead_uid || undefined,   // GA4 dedup key
            transport_type: 'beacon'
          });
          if (T.googleAds && T.googleAdsLabel) {
            gtag('event', 'conversion', {
              send_to: T.googleAds + '/' + T.googleAdsLabel,
              transaction_id: data.lead_uid || undefined,
              transport_type: 'beacon'
            });
          }
        }
        if (typeof fbq !== 'undefined' && data.event_id) {
          fbq('track', 'Lead', { content_category: service }, { eventID: data.event_id });
        }
        if (typeof lintrk !== 'undefined' && T.linkedinConversionId) {
          lintrk('track', { conversion_id: T.linkedinConversionId });
        }

        // Go straight to the thank-you page. Nothing is revealed on this page
        // first — any inline panel would only flash for a few milliseconds
        // before the navigation and read as a glitch.
        redirecting = true;
        // A short pause gives the pixel's own request a chance to leave the
        // browser. Meta's CAPI event is already safely recorded, so this is
        // belt-and-braces for match quality, not correctness.
        setTimeout(() => window.location.assign('thank-you.html'), 250);
      } else {
        showError(data.message || 'Something went wrong. Please email us directly.');
      }
    } catch {
      showError('Network error. Please check your connection and try again.');
    } finally {
      // Keep the button in its "Sending…" state while the browser navigates,
      // so nothing visibly resets in the moment before the page changes.
      if (!redirecting) setLoading(false);
    }
  });

  // Clear error styling on input
  leadForm.querySelectorAll('.form-input').forEach(f => {
    f.addEventListener('input', () => f.classList.remove('error'));
  });
}

function setLoading(on) {
  submitBtn.disabled = on;
  btnText.textContent = on ? 'Sending…' : 'Send My Request';
  btnArrow.classList.toggle('hidden', on);
  btnSpinner.classList.toggle('hidden', !on);
}
function showError(msg) {
  formError.textContent = msg;
  formError.classList.remove('hidden');
  formError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function hideError() { formError.classList.add('hidden'); }

/* ── GA4 section view tracking ───────────────────────────── */
const sectionLabels = {
  home: 'Hero', services: 'Services', clients: 'Who We Serve',
  'why-us': 'Why Heliora', process: 'Process', contact: 'Contact'
};
const ga4SectionObs = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting && typeof gtag !== 'undefined') {
      gtag('event', 'section_view', {
        event_category: 'Engagement',
        event_label: sectionLabels[entry.target.id] || entry.target.id
      });
    }
  });
}, { threshold: 0.4 });
document.querySelectorAll('section[id]').forEach(s => ga4SectionObs.observe(s));

/* ── GA4 CTA click tracking ──────────────────────────────── */
document.querySelectorAll('a[href="#contact"]').forEach(btn => {
  btn.addEventListener('click', () => {
    if (typeof gtag !== 'undefined') {
      gtag('event', 'cta_click', { event_category: 'Lead', event_label: btn.textContent.trim() });
    }
  });
});

/* ── Consultation modal ──────────────────────────────────── */
(function () {
  const modal        = document.getElementById('consultation-modal');
  const backdrop     = document.getElementById('modal-backdrop');
  const closeBtn     = document.getElementById('modal-close');
  const successClose = document.getElementById('modal-success-close');
  if (!modal) return;

  function openModal() {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    // Opening the consultation form is the strongest qualified-engagement
    // signal short of submitting, so it fires ViewContent immediately -
    // no dwell-time condition needed.
    if (typeof window.heliora_fireViewContent === 'function') {
      window.heliora_fireViewContent('form_open');
    }
    const first = modal.querySelector('input:not([type="hidden"]):not([style*="display:none"])');
    if (first) setTimeout(() => first.focus(), 50);
  }

  function closeModal() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.open-consultation-modal').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      if (typeof gtag !== 'undefined') {
        gtag('event', 'cta_click', { event_category: 'Lead', event_label: el.textContent.trim() });
      }
      openModal();
    });
  });

  backdrop.addEventListener('click', closeModal);
  closeBtn.addEventListener('click', closeModal);
  if (successClose) successClose.addEventListener('click', closeModal);

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });
}());

/* ── Back to top ─────────────────────────────────────────── */
(function () {
  const btn = document.getElementById('back-to-top');
  if (!btn) return;

  const toggle = () => btn.classList.toggle('show', window.scrollY > 600);
  toggle();
  window.addEventListener('scroll', toggle, { passive: true });

  btn.addEventListener('click', () => {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
  });
}());
