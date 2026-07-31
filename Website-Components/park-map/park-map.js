/* ==========================================================================
   Park Map — script (v2)

   Paste into Oxygen's global JS (footer scripts), or wrap in
   <script>...</script> inside its own Code Block on the same page as
   park-map.php.

   Requires the v2 markup (park-map.php) — every trigger carries
   data-pmap-map-trigger="{n}" and its backdrop carries
   data-pmap-instance="{n}".

   After an AJAX / soft navigation that re-renders the section, call
   window.pmapParkMap.init() or dispatch a 'pmap:refresh' event on document.
   ========================================================================== */

(function () {
	'use strict';

	var FOCUSABLE = [
		'a[href]', 'area[href]', 'button', 'input', 'select', 'textarea',
		'iframe', 'object', 'embed', 'summary', 'audio[controls]',
		'video[controls]', '[contenteditable]', '[tabindex]'
	].join(',');

	/* ── Shared, reference-counted scroll lock ─────────────────────────────
	   Never writes body.style.overflow directly: a nav drawer or cookie
	   banner using the same technique would otherwise unlock the page when
	   whichever component closes first. Compensates for the vanishing
	   scrollbar so the page doesn't jump sideways on open. */

	var ScrollLock = (function () {
		var depth   = 0;
		var savedY  = 0;
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
				document.documentElement.classList.add( 'pmap-scroll-locked' );
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
				document.documentElement.classList.remove( 'pmap-scroll-locked' );
			}
		}

		return { lock: lock, unlock: unlock };
	})();

	var openStack = [];   // topmost open dialog is last
	var registry  = {};   // instance id -> controller

	function tabbable( root ) {
		return Array.prototype.filter.call(
			root.querySelectorAll( FOCUSABLE ),
			function ( el ) {
				if ( el.disabled || el.getAttribute( 'aria-hidden' ) === 'true' ) return false;
				if ( el.tabIndex < 0 ) return false;
				if ( el.type === 'hidden' ) return false;
				return !! ( el.offsetWidth || el.offsetHeight || el.getClientRects().length );
			}
		);
	}

	/* ── One controller per map instance ───────────────────────────────── */

	function createController( id, trigger, backdrop ) {
		var dialog   = backdrop.querySelector( '.pmap-modal' ) || backdrop;
		var closeBtn = backdrop.querySelector( '.pmap-modal__close' );
		var img      = backdrop.querySelector( '[data-pmap-src]' );

		var listeners   = [];
		var lastFocused = null;
		var imgLoaded   = false;
		var pressedOnBackdrop = false;

		// Reparent to <body> so position:fixed can't be trapped by an
		// ancestor with transform / filter / will-change.
		if ( backdrop.parentNode !== document.body ) {
			document.body.appendChild( backdrop );
		}

		var controller = {
			id: id,
			isOpen: isOpen,
			open: open,
			close: close,
			adopt: adopt,
			destroy: destroy
		};

		function on( target, type, fn, opts ) {
			target.addEventListener( type, fn, opts );
			listeners.push( [ target, type, fn, opts ] );
		}

		function isOpen() {
			return backdrop.classList.contains( 'pmap-modal-backdrop--open' );
		}

		// The modal image ships with no src at all (see park-map.php), so it
		// isn't a second full-size download on every page load. srcset is
		// assigned before src so the browser picks a candidate rather than
		// fetching the original and then reconsidering.
		function loadImage() {
			if ( imgLoaded || ! img ) return;
			imgLoaded = true;
			if ( img.getAttribute( 'data-pmap-srcset' ) ) {
				img.setAttribute( 'srcset', img.getAttribute( 'data-pmap-srcset' ) );
			}
			img.setAttribute( 'src', img.getAttribute( 'data-pmap-src' ) );
		}

		function open() {
			if ( isOpen() ) return;
			lastFocused = document.activeElement;
			loadImage();

			backdrop.removeAttribute( 'inert' );
			backdrop.classList.add( 'pmap-modal-backdrop--open' );
			ScrollLock.lock();
			openStack.push( controller );

			// preventScroll: the backdrop is a scroll container and the card
			// starts translated 16px down, so a plain focus() scrolls the
			// backdrop to chase the button mid-animation and the modal
			// visibly lurches on open.
			if ( closeBtn ) {
				closeBtn.focus( { preventScroll: true } );
			} else {
				dialog.setAttribute( 'tabindex', '-1' );
				dialog.focus( { preventScroll: true } );
			}
		}

		function close() {
			if ( ! isOpen() ) return;

			backdrop.classList.remove( 'pmap-modal-backdrop--open' );
			ScrollLock.unlock();
			openStack = openStack.filter( function ( c ) { return c !== controller; } );

			var restore = null;
			if ( lastFocused && document.contains( lastFocused ) && lastFocused.focus ) {
				restore = lastFocused;
			} else if ( trigger && document.contains( trigger ) ) {
				restore = trigger;
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

			// inert is applied *after* focus moves out. CSS also sets
			// visibility:hidden, which covers browsers without inert.
			backdrop.setAttribute( 'inert', '' );
		}

		function onKeydown( e ) {
			if ( e.defaultPrevented || ! isOpen() ) return;
			// Only the topmost open dialog reacts, so a nested or sibling
			// modal isn't closed out from under itself.
			if ( openStack[ openStack.length - 1 ] !== controller ) return;

			if ( e.key === 'Escape' || e.key === 'Esc' ) {
				e.preventDefault();
				close();
				return;
			}
			if ( e.key !== 'Tab' ) return;

			// Focus trap: aria-modal alone doesn't stop the Tab key.
			var items = tabbable( dialog );
			if ( ! items.length ) {
				e.preventDefault();
				dialog.setAttribute( 'tabindex', '-1' );
				dialog.focus();
				return;
			}
			var first  = items[ 0 ];
			var last   = items[ items.length - 1 ];
			var active = document.activeElement;

			if ( ! dialog.contains( active ) ) {
				e.preventDefault();
				( e.shiftKey ? last : first ).focus();
			} else if ( e.shiftKey && active === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && active === last ) {
				e.preventDefault();
				first.focus();
			}
		}

		function bindTrigger( el ) {
			on( el, 'click', open );
			// Warm the image on hover/focus so first open isn't a blank frame.
			on( el, 'pointerenter', loadImage );
			on( el, 'focus', loadImage );
			el.dataset.pmapBound = '1';
		}

		function adopt( newTrigger ) {
			trigger = newTrigger;
			bindTrigger( newTrigger );
		}

		function destroy() {
			if ( isOpen() ) close();
			listeners.forEach( function ( l ) {
				l[ 0 ].removeEventListener( l[ 1 ], l[ 2 ], l[ 3 ] );
			} );
			listeners = [];
			if ( trigger ) delete trigger.dataset.pmapBound;
			if ( backdrop.parentNode ) backdrop.parentNode.removeChild( backdrop );
			delete registry[ id ];
		}

		bindTrigger( trigger );
		if ( closeBtn ) on( closeBtn, 'click', close );

		// Click-outside, without the drag-from-inside and scrollbar-click
		// false positives a bare click handler gives you.
		on( backdrop, 'pointerdown', function ( e ) {
			pressedOnBackdrop = ( e.target === backdrop ) &&
				e.offsetX <= backdrop.clientWidth &&
				e.offsetY <= backdrop.clientHeight;
		} );
		on( backdrop, 'click', function ( e ) {
			if ( pressedOnBackdrop && e.target === backdrop ) close();
			pressedOnBackdrop = false;
		} );

		on( document, 'keydown', onKeydown );

		backdrop.dataset.pmapBound = '1';
		registry[ id ] = controller;
		return controller;
	}

	/* ── Init / re-init ───────────────────────────────────────────────── */

	function init() {
		var triggers = document.querySelectorAll( '[data-pmap-map-trigger]' );

		Array.prototype.forEach.call( triggers, function ( trigger ) {
			if ( trigger.dataset.pmapBound === '1' ) return;

			var id = trigger.getAttribute( 'data-pmap-map-trigger' ) || 'default';

			// Prefer a freshly rendered, unclaimed backdrop for this id.
			var fresh = null;
			var candidates = document.querySelectorAll(
				'.pmap-modal-backdrop[data-pmap-instance="' + id.replace( /"/g, '' ) + '"]'
			);
			Array.prototype.forEach.call( candidates, function ( c ) {
				if ( c.dataset.pmapBound !== '1' ) fresh = c;
			} );

			if ( fresh ) {
				// A stale controller from a previous render still owns a
				// backdrop parked in <body>. Tear it down first.
				if ( registry[ id ] ) registry[ id ].destroy();
				createController( id, trigger, fresh );
			} else if ( registry[ id ] ) {
				// Only the trigger was re-rendered; reuse the live modal.
				registry[ id ].adopt( trigger );
			}
		} );
	}

	window.pmapParkMap = { init: init, instances: registry };
	document.addEventListener( 'pmap:refresh', init );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();