/* ==========================================================================
   Typhoon Texas — Attraction Page JS
   ========================================================================== */

(function () {
  'use strict';

  /* ── Sticky nav + hero wave scroll effect ────────────────────────────── */

  const stickyNav  = document.querySelector('.ap-sticky-nav');
  const heroEl     = document.querySelector('.ap-hero');
  const heroHeight = heroEl?.offsetHeight ?? 400;
  const waveBack   = document.querySelector('.ap-hero__wave-layer--back');
  const waveFront  = document.querySelector('.ap-hero__wave-layer--front');

  const onScroll = () => {
    const scrollY = window.scrollY;

    // Sticky nav: slide in after 60% of hero height
    if (stickyNav) {
      stickyNav.classList.toggle('ap-sticky-nav--visible', scrollY > heroHeight * 0.6);
    }

    // Scroll-driven wave parallax — layers drift in opposite directions
    // translateX range is kept small so motion is subtle, not distracting
    if (waveBack || waveFront) {
      const progress = Math.min(scrollY / heroHeight, 1);
      const backX  = -(progress * 10).toFixed(3);  // drifts left  0 → -10%
      const frontX =  (progress *  6).toFixed(3);  // drifts right 0 →  +6%
      if (waveBack)  waveBack.style.transform  = `translateX(${backX}%)`;
      if (waveFront) waveFront.style.transform = `translateX(${frontX}%)`;
    }
  };

  // rAF-throttled: scroll fires far more often than the display can paint,
  // so batch reads/writes to at most once per frame instead of every event.
  let scrollTicking = false;
  window.addEventListener('scroll', () => {
    if (scrollTicking) return;
    scrollTicking = true;
    requestAnimationFrame(() => {
      onScroll();
      scrollTicking = false;
    });
  }, { passive: true });

  /* ── Scroll-reveal animations ─────────────────────────────────────────── */

  const animEls = document.querySelectorAll('.ap-animate');

  if (animEls.length && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach(e => {
          if (e.isIntersecting) {
            e.target.classList.add('ap-animate--visible');
            io.unobserve(e.target);
          }
        });
      },
      { threshold: 0.12 }
    );
    animEls.forEach(el => io.observe(el));
  } else {
    // Fallback: just show everything
    animEls.forEach(el => el.classList.add('ap-animate--visible'));
  }

  /* ── Map modal ────────────────────────────────────────────────────────── */

  const mapTrigger  = document.querySelector('[data-ap-map-trigger]');
  const mapBackdrop = document.querySelector('.ap-modal-backdrop');
  const mapClose    = document.querySelector('.ap-modal__close');

  function openModal() {
    if (!mapBackdrop) return;
    mapBackdrop.classList.add('ap-modal-backdrop--open');
    mapBackdrop.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    mapClose?.focus();
  }

  function closeModal() {
    if (!mapBackdrop) return;
    mapBackdrop.classList.remove('ap-modal-backdrop--open');
    mapBackdrop.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    mapTrigger?.focus();
  }

  mapTrigger?.addEventListener('click', openModal);
  mapClose?.addEventListener('click', closeModal);
  mapBackdrop?.addEventListener('click', (e) => {
    if (e.target === mapBackdrop) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });

  /* ── Gallery lightbox (minimal) ───────────────────────────────────────── */

  const galleryItems = document.querySelectorAll('.ap-gallery__item[data-src]');

  galleryItems.forEach(item => {
    item.setAttribute('role', 'button');
    item.setAttribute('tabindex', '0');

    const open = () => {
      const src = item.dataset.src;
      const alt = item.querySelector('img')?.alt ?? '';
      showLightbox(src, alt);
    };

    item.addEventListener('click', open);
    item.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
  });

  function showLightbox(src, alt) {
    const lb = document.createElement('div');
    lb.style.cssText = [
      'position:fixed;inset:0;z-index:300;background:rgba(10,20,60,.92)',
      'display:flex;align-items:center;justify-content:center;padding:1.5rem',
      'cursor:zoom-out'
    ].join(';');

    const img = document.createElement('img');
    img.src = src;
    img.alt = alt;
    img.style.cssText = 'max-width:100%;max-height:90vh;border-radius:0.75rem;object-fit:contain';

    lb.appendChild(img);
    document.body.appendChild(lb);
    document.body.style.overflow = 'hidden';

    const dismiss = () => {
      lb.remove();
      document.body.style.overflow = '';
    };

    lb.addEventListener('click', dismiss);
    document.addEventListener('keydown', function esc(e) {
      if (e.key === 'Escape') { dismiss(); document.removeEventListener('keydown', esc); }
    });
  }

})();
