/* Superseded — enqueue tt-carousel.js instead (Website-Components/tt-carousel.js) */
/**
 * CBV Carousel — arrow navigation + dots + infinite loop
 * Handles all .cbv-carousel sections on the page (events and promotions).
 * Place <script> at end of <body> or enqueue with defer.
 */
(function () {
  'use strict';

  var SCROLL_AMOUNT = 0.85;

  function initCarousel(section) {
    var track   = section.querySelector('.cbv-carousel__track');
    var btnPrev = section.querySelector('.cbv-carousel__arrow--prev');
    var btnNext = section.querySelector('.cbv-carousel__arrow--next');
    var dotsEl  = section.querySelector('.cbv-carousel__dots');

    if (!track || !btnPrev || !btnNext) return;

    btnPrev.removeAttribute('hidden');
    btnNext.removeAttribute('hidden');

    // ── Dots ─────────────────────────────────────────────────────────────────
    var dots = [];

    function buildDots() {
      if (!dotsEl) return;
      dotsEl.innerHTML = '';
      dots = [];
      var cards = Array.from(track.querySelectorAll('.cbv-card'));
      cards.forEach(function (card, i) {
        var dot = document.createElement('button');
        dot.className = 'cbv-carousel__dot';
        dot.setAttribute('aria-label', 'Go to item ' + (i + 1));
        dot.addEventListener('click', function () {
          var offset = card.getBoundingClientRect().left - track.getBoundingClientRect().left;
          track.scrollBy({ left: offset, behavior: 'smooth' });
        });
        dotsEl.appendChild(dot);
        dots.push(dot);
      });
      updateDots();
    }

    function updateDots() {
      if (!dots.length) return;
      var trackLeft = track.getBoundingClientRect().left;
      var cards = track.querySelectorAll('.cbv-card');
      var active = 0;
      var minDist = Infinity;
      cards.forEach(function (card, i) {
        var dist = Math.abs(card.getBoundingClientRect().left - trackLeft);
        if (dist < minDist) { minDist = dist; active = i; }
      });
      dots.forEach(function (dot, i) {
        dot.classList.toggle('cbv-carousel__dot--active', i === active);
      });
    }

    // ── Infinite loop ─────────────────────────────────────────────────────────
    function getScrollStep(direction = 'next') {
      var cards = Array.from(track.querySelectorAll('.cbv-card'));
    
      if (!cards.length) return track.clientWidth;
    
      var trackLeft = track.getBoundingClientRect().left;
    
      var targetCard = null;
    
      cards.forEach(function(card) {
        var cardLeft = card.getBoundingClientRect().left;
    
        if (direction === 'next') {
          if (cardLeft > trackLeft + 10 && !targetCard) {
            targetCard = card;
          }
        } else {
          if (cardLeft < trackLeft - 10) {
            targetCard = card;
          }
        }
      });
    
      if (!targetCard) {
        targetCard = direction === 'next'
          ? cards[0]
          : cards[cards.length - 1];
      }
    
      return targetCard.offsetLeft - track.scrollLeft;
    }

    btnPrev.addEventListener('click', function () {
      if (track.scrollLeft <= 2) {
        track.scrollTo({ left: track.scrollWidth, behavior: 'smooth' });
      } else {
        track.scrollBy({ left: getScrollStep('prev'), behavior: 'smooth' });
      }
    });

    btnNext.addEventListener('click', function () {
      if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 2) {
        track.scrollTo({ left: 0, behavior: 'smooth' });
      } else {
        track.scrollBy({ left: getScrollStep('next'), behavior: 'smooth' });
      }
    });

    var scrollTimer = null;
    track.addEventListener('scroll', function () {
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(updateDots, 80);
    }, { passive: true });

    var ro = window.ResizeObserver ? new ResizeObserver(buildDots) : null;
    if (ro) { ro.observe(track); }
    else     { window.addEventListener('resize', buildDots, { passive: true }); }

    buildDots();
  }

  function init() {
    document.querySelectorAll('.cbv-carousel').forEach(initCarousel);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
