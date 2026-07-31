/* ==========================================================================
   Photo Gallery — script (v2)

   Paste into Oxygen's global JS (footer scripts), or wrap in
   <script>...</script> inside its own Code Block on the same page as
   photo-gallery.php.

   Requires the v2 markup (photo-gallery.php) — every gallery root and its
   dialog carry data-rgallery-instance="{n}".

   After an AJAX / soft navigation that re-renders the section, call
   window.rgalleryPhotoGallery.init() or dispatch an 'rgallery:refresh' event
   on document.

   Focus trapping, Escape, and background inertness come free from native
   <dialog>.showModal(). Scroll locking does not — see ScrollLock below.
   ========================================================================== */

(function () {
	'use strict';

	/* ── Shared, reference-counted scroll lock ─────────────────────────────
	   Published on window so park-map and any other modal component share one
	   depth counter and one saved scroll position. Two components each running
	   their own lock would fight over body.style.paddingRight (and, on iOS,
	   over the saved scroll offset) whenever both are open.

	   Never writes body.style.overflow directly: a nav drawer or cookie banner
	   using the same technique would otherwise unlock the page when whichever
	   component closes first. Compensates for the vanishing scrollbar so the
	   page doesn't jump sideways on open. */

	var ScrollLock = window.PyekScrollLock || ( window.PyekScrollLock = ( function () {
		var depth        = 0;
		var savedY       = 0;
		var savedPadding = '';
		var isIOS = /iP(hone|ad|od)/.test( navigator.userAgent ) ||
			( navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1 );

		function lock() {
			depth++;
			if ( depth > 1 ) return;

			var gutter = window.innerWidth - document.documentElement.clientWidth;
			savedPadding = document.body.style.paddingRight;
			if ( gutter > 0 ) {
				document.body.style.paddingRight = gutter + 'px';
			}

			if ( isIOS ) {
				// overflow:hidden on <body> doesn't reliably stop rubber-band
				// scrolling in iOS Safari; pinning the body does.
				savedY = window.pageYOffset || 0;
				document.body.style.position = 'fixed';
				document.body.style.top      = ( -savedY ) + 'px';
				document.body.style.left     = '0';
				document.body.style.right    = '0';
				document.body.style.width    = '100%';
			} else {
				document.documentElement.classList.add( 'pyek-scroll-locked' );
			}
		}

		function unlock() {
			if ( depth === 0 ) return;
			depth--;
			if ( depth > 0 ) return;

			document.body.style.paddingRight = savedPadding;

			if ( isIOS ) {
				document.body.style.position = '';
				document.body.style.top      = '';
				document.body.style.left     = '';
				document.body.style.right    = '';
				document.body.style.width    = '';
				window.scrollTo( 0, savedY );
			} else {
				document.documentElement.classList.remove( 'pyek-scroll-locked' );
			}
		}

		return { lock: lock, unlock: unlock };
	} )() );

	var registry = {};   // instance id -> controller

	/* ── One controller per gallery instance ───────────────────────────── */

	// Matches the .pmap-modal transition in park-map.css. The class is added a
	// frame after showModal() and removed EXIT_MS before the dialog actually
	// closes, which is what gives the gallery the same fade-and-scale as the
	// park map instead of native <dialog>'s instant show/hide.
	var VISIBLE_CLASS = 'rgallery-dialog--visible';
	var EXIT_MS       = 250;

	var reduceMotion = window.matchMedia
		? window.matchMedia( '(prefers-reduced-motion: reduce)' )
		: null;

	function motionOff() {
		return !! ( reduceMotion && reduceMotion.matches );
	}

	function createController( id, root, dialog ) {
		var buttons   = Array.prototype.slice.call( root.querySelectorAll( '.rgallery__btn' ) );
		var modal     = dialog.querySelector( '.rgallery-modal' ) || dialog;
		var img       = dialog.querySelector( '.rgallery-modal__img' );
		var captionEl = dialog.querySelector( '.rgallery-modal__caption' );
		var countEl   = dialog.querySelector( '.rgallery-modal__count' );
		var closeBtn  = dialog.querySelector( '.rgallery-modal__close' );
		var prevBtn   = dialog.querySelector( '.rgallery-modal__nav--prev' );
		var nextBtn   = dialog.querySelector( '.rgallery-modal__nav--next' );

		var listeners   = [];
		var preloaded   = {};
		var current     = 0;
		var lastFocused = null;
		var locked      = false;
		var exitTimer   = null;
		var pressedOnBackdrop = false;

		// Reparent to <body> so the top layer can't be clipped by an ancestor
		// with overflow / transform / filter / will-change.
		if ( dialog.parentNode !== document.body ) {
			document.body.appendChild( dialog );
		}

		var controller = {
			id: id,
			open: open,
			close: close,
			destroy: destroy,
			isOpen: function () { return !! dialog.open; }
		};

		function on( target, type, fn, opts ) {
			target.addEventListener( type, fn, opts );
			listeners.push( [ target, type, fn, opts ] );
		}

		function wrap( index ) {
			var n = buttons.length;
			return ( ( index % n ) + n ) % n;
		}

		/* Warm a photo without displaying it, so prev/next isn't a blank frame.
		   sizes is assigned before srcset so the preloader picks the same
		   candidate the dialog image will — otherwise it warms the wrong file
		   and the fetch is wasted. */
		function preload( index ) {
			if ( ! buttons.length ) return;
			var i = wrap( index );
			if ( preloaded[ i ] ) return;
			preloaded[ i ] = true;

			var btn = buttons[ i ];
			var src = btn.getAttribute( 'data-rgallery-full' );
			if ( ! src ) return;

			var pre    = new Image();
			var srcset = btn.getAttribute( 'data-rgallery-srcset' );
			if ( srcset ) {
				pre.sizes  = img ? img.getAttribute( 'sizes' ) || '' : '';
				pre.srcset = srcset;
			}
			pre.src = src;
		}

		function settled() {
			modal.classList.remove( 'rgallery-modal--loading' );
		}

		function show( index ) {
			if ( ! buttons.length || ! img ) return;

			current = wrap( index );
			var btn = buttons[ current ];

			modal.classList.add( 'rgallery-modal--loading' );

			img.alt = btn.getAttribute( 'data-rgallery-alt' ) || '';

			// srcset before src, so the browser resolves a candidate rather
			// than fetching the fallback and then reconsidering.
			var srcset = btn.getAttribute( 'data-rgallery-srcset' );
			if ( srcset ) {
				img.setAttribute( 'srcset', srcset );
			} else {
				img.removeAttribute( 'srcset' );
			}
			img.setAttribute( 'src', btn.getAttribute( 'data-rgallery-full' ) || '' );
			preloaded[ current ] = true;

			// Re-showing the same photo doesn't change src, so no load event
			// fires and the loading class would stick.
			if ( img.complete && img.naturalWidth ) settled();

			if ( captionEl ) {
				captionEl.textContent = btn.getAttribute( 'data-rgallery-caption' ) || '';
			}
			if ( countEl ) {
				countEl.textContent = ( current + 1 ) + ' / ' + buttons.length;
			}

			preload( current + 1 );
			preload( current - 1 );
		}

		function open( index ) {
			// Re-opening mid-exit: cancel the pending close and fade back in
			// rather than letting the old timer tear the dialog down.
			if ( exitTimer ) {
				clearTimeout( exitTimer );
				exitTimer = null;
			}
			if ( dialog.open ) {
				dialog.classList.add( VISIBLE_CLASS );
				show( index );
				return;
			}

			lastFocused = document.activeElement;

			if ( typeof dialog.showModal === 'function' ) {
				dialog.showModal();
			} else {
				// No <dialog> support: still show something rather than
				// swallowing the click.
				dialog.setAttribute( 'open', '' );
			}

			if ( ! locked ) {
				ScrollLock.lock();
				locked = true;
			}

			// Populated *after* the dialog is rendered — a polite live region
			// that changes while still display:none isn't announced, so v1
			// silently dropped the count on first open.
			show( index );

			if ( motionOff() ) {
				dialog.classList.add( VISIBLE_CLASS );
			} else {
				// <dialog> goes display:none -> shown, and a transition can't
				// start from a state the browser never painted. Give it one
				// frame in the closed state first.
				requestAnimationFrame( function () {
					requestAnimationFrame( function () {
						if ( dialog.open ) dialog.classList.add( VISIBLE_CLASS );
					} );
				} );
			}
		}

		function close() {
			if ( ! dialog.open || exitTimer ) return;

			dialog.classList.remove( VISIBLE_CLASS );

			if ( motionOff() ) {
				finishClose();
			} else {
				// A timer rather than transitionend: transitionend can be
				// missed if the dialog is hidden or the transition is
				// interrupted, and a modal that never closes is unrecoverable.
				exitTimer = setTimeout( finishClose, EXIT_MS );
			}
		}

		function finishClose() {
			exitTimer = null;
			if ( ! dialog.open ) return;
			if ( typeof dialog.close === 'function' ) {
				dialog.close();          // fires 'close' -> onClose
			} else {
				dialog.removeAttribute( 'open' );
				onClose();
			}
		}

		// Bound to the dialog's own 'close' event so Escape, the close button,
		// and a backdrop click all unwind through one path.
		function onClose() {
			if ( locked ) {
				ScrollLock.unlock();
				locked = false;
			}
			dialog.classList.remove( VISIBLE_CLASS );
			settled();

			// The src is deliberately left in place: it's already decoded, so
			// reopening is instant, and clearing it would leave an <img> that
			// has alt text but no source.

			// Native <dialog> restores focus itself, but not when the element
			// it remembered has since been removed from the DOM.
			var restore = null;
			if ( lastFocused && document.contains( lastFocused ) && lastFocused.focus ) {
				restore = lastFocused;
			} else if ( buttons[ current ] && document.contains( buttons[ current ] ) ) {
				restore = buttons[ current ];
			}
			if ( restore ) {
				restore.focus();
			} else {
				// Don't silently drop focus to nowhere.
				document.body.setAttribute( 'tabindex', '-1' );
				document.body.focus();
				document.body.removeAttribute( 'tabindex' );
			}
			lastFocused = null;
		}

		function destroy() {
			// Skip the exit transition — the element is about to be removed.
			if ( exitTimer ) {
				clearTimeout( exitTimer );
				exitTimer = null;
			}
			dialog.classList.remove( VISIBLE_CLASS );
			finishClose();
			// The 'close' event is queued as a task, not fired synchronously,
			// so onClose would run *after* the listeners below are torn down —
			// unlock here instead or the page stays frozen. onClose is
			// idempotent, so whichever gets there first wins.
			if ( locked ) {
				ScrollLock.unlock();
				locked = false;
			}
			listeners.forEach( function ( l ) {
				l[ 0 ].removeEventListener( l[ 1 ], l[ 2 ], l[ 3 ] );
			} );
			listeners = [];
			if ( dialog.parentNode ) dialog.parentNode.removeChild( dialog );
			delete root.dataset.rgalleryBound;
			delete registry[ id ];
		}

		/* ── Wiring ────────────────────────────────────────────────────── */

		buttons.forEach( function ( btn, i ) {
			on( btn, 'click', function () { open( i ); } );
			// Warm the full-size file on hover/focus so the first open isn't
			// a blank frame.
			on( btn, 'pointerenter', function () { preload( i ); } );
			on( btn, 'focus', function () { preload( i ); } );
		} );

		if ( closeBtn ) on( closeBtn, 'click', close );
		if ( prevBtn )  on( prevBtn, 'click', function () { show( current - 1 ); } );
		if ( nextBtn )  on( nextBtn, 'click', function () { show( current + 1 ); } );

		if ( img ) {
			on( img, 'load', settled );
			on( img, 'error', settled );
		}

		/* Click-outside. The dialog fills the viewport and the white card sits
		   inside it, so "target is the dialog itself" means the click landed
		   on the tinted area around the card — the same test park-map.js runs
		   against its backdrop element. Clicks on the card, including the
		   letterboxing beside a portrait photo, hit .rgallery-modal instead
		   and are correctly ignored.
		   The pointerdown pairing stops a drag that starts on the image and
		   releases over the backdrop from closing the viewer. */
		on( dialog, 'pointerdown', function ( e ) {
			pressedOnBackdrop = ( e.target === dialog );
		} );
		on( dialog, 'click', function ( e ) {
			if ( pressedOnBackdrop && e.target === dialog ) close();
			pressedOnBackdrop = false;
		} );

		on( dialog, 'keydown', function ( e ) {
			if ( e.defaultPrevented || buttons.length < 2 ) return;
			// preventDefault, or the arrows scroll the page behind the modal
			// as well as advancing the photo.
			if ( e.key === 'ArrowLeft' ) {
				e.preventDefault();
				show( current - 1 );
			} else if ( e.key === 'ArrowRight' ) {
				e.preventDefault();
				show( current + 1 );
			}
		} );

		on( dialog, 'close', onClose );

		// Escape would otherwise close the dialog instantly, skipping the exit
		// transition. Cancel the native close and run ours instead.
		on( dialog, 'cancel', function ( e ) {
			e.preventDefault();
			pressedOnBackdrop = false;
			close();
		} );

		root.dataset.rgalleryBound = '1';
		registry[ id ] = controller;
		return controller;
	}

	/* ── Init / re-init ───────────────────────────────────────────────── */

	function init() {
		var roots = document.querySelectorAll( '.rgallery[data-rgallery-instance]' );

		Array.prototype.forEach.call( roots, function ( root ) {
			if ( root.dataset.rgalleryBound === '1' ) return;

			var id = root.getAttribute( 'data-rgallery-instance' ) || 'default';

			// A controller from a previous render still owns a dialog parked in
			// <body>. Tear it down before the fresh markup claims the id, or
			// every re-render leaks another orphaned dialog and its listeners.
			if ( registry[ id ] ) registry[ id ].destroy();

			// The dialog always ships inside its root; it only leaves once a
			// controller adopts it.
			var dialog = root.querySelector( '.rgallery-dialog' );
			if ( ! dialog || ! root.querySelector( '.rgallery__btn' ) ) return;

			createController( id, root, dialog );
		} );
	}

	window.rgalleryPhotoGallery = { init: init, instances: registry };
	document.addEventListener( 'rgallery:refresh', init );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
