/* ════════════════════════════════════════════════════════
   Heliora Consulting Limited — Main JavaScript
   ════════════════════════════════════════════════════════ */
'use strict';

/* ── UTM & page URL capture ──────────────────────────────── */
(function captureUTM() {
  const p = new URLSearchParams(window.location.search);
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
  set('f_utm_source',   p.get('utm_source')   || '');
  set('f_utm_medium',   p.get('utm_medium')   || '');
  set('f_utm_campaign', p.get('utm_campaign') || '');
  set('f_page_url',     window.location.href);
})();

/* ── Navbar scroll behaviour ─────────────────────────────── */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 40);
}, { passive: true });

/* ── Mobile menu ─────────────────────────────────────────── */
const menuBtn    = document.getElementById('menu-btn');
const mobileMenu = document.getElementById('mobile-menu');

menuBtn.addEventListener('click', () => {
  const isOpen = !mobileMenu.classList.contains('hidden');
  mobileMenu.classList.toggle('hidden', isOpen);
  menuBtn.classList.toggle('open', !isOpen);
  menuBtn.setAttribute('aria-expanded', String(!isOpen));
});

mobileMenu.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', () => {
    mobileMenu.classList.add('hidden');
    menuBtn.classList.remove('open');
    menuBtn.setAttribute('aria-expanded', 'false');
  });
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

    // Required field validation
    let valid = true;
    leadForm.querySelectorAll('[required]').forEach(f => {
      f.classList.remove('error');
      if (!f.value.trim()) { f.classList.add('error'); valid = false; }
    });
    if (!valid) { showError('Please complete all required fields.'); return; }

    // Email validation
    const emailEl = leadForm.querySelector('[name="email"]');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
      emailEl.classList.add('error');
      showError('Please enter a valid email address.');
      return;
    }

    setLoading(true);
    hideError();

    try {
      const res  = await fetch('submit-lead.php', {
        method: 'POST',
        body:   new FormData(leadForm),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();

      if (data.success) {
        if (typeof gtag !== 'undefined') {
          gtag('event', 'generate_lead', {
            event_category: 'Lead',
            event_label: new FormData(leadForm).get('service'),
            value: 1
          });
        }
        leadForm.classList.add('hidden');
        formSuccess.classList.remove('hidden');
        showToast('Request sent! Check your inbox for confirmation.');
      } else {
        showError(data.message || 'Something went wrong. Please email us directly.');
      }
    } catch {
      showError('Network error. Please check your connection and try again.');
    } finally {
      setLoading(false);
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
