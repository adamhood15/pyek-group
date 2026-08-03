/* ==========================================================================
   Photo Gallery — script (v3)

   REQUIRES pyek-modal.js (../pyek-modal/), which owns opening, closing,
   Escape, the focus trap, focus restoration and the page scroll lock. This
   file adds only what is specific to a gallery: which photo is showing,
   prev/next, the caption and the counter.

   Paste into Oxygen's global JS (footer scripts), or wrap in <script>...
   </script> inside its own Code Block on the same page as photo-gallery.php.

   Every listener is delegated from `document`, so load order doesn't matter,
   any number of galleries work, and markup added later (AJAX, a soft
   navigation, an Oxygen repeater) needs no re-init call.
   ========================================================================== */

(function () {
	"use strict";

	/* Collect the three things every handler needs, starting from any element
	   inside a gallery — a thumbnail, a nav arrow, or the dialog itself. */
	function readGallery(elementInsideGallery) {
		var root = elementInsideGallery.closest(".rgallery");
		if (!root) return null;

		var dialog = root.querySelector(".rgallery-dialog");
		if (!dialog) return null;

		return {
			dialog: dialog,
			photo: dialog.querySelector(".rgallery-modal__img"),
			thumbButtons: Array.prototype.slice.call(root.querySelectorAll(".rgallery__btn"))
		};
	}

	function clearLoadingState(event) {
		event.currentTarget.classList.remove("is-loading");
	}

	/* Fetch a full-size photo without displaying it, so prev/next isn't a blank
	   frame. `sizes` is assigned before `srcset` so the preloader resolves the
	   same candidate the lightbox will — otherwise it warms the wrong file and
	   the request is wasted. */
	function preloadPhoto(thumbButton, lightboxSizes) {
		if (!thumbButton || thumbButton.dataset.preloaded) return;
		thumbButton.dataset.preloaded = "1";

		var warmImage = new Image();
		if (thumbButton.dataset.fullSrcset) {
			warmImage.sizes = lightboxSizes;
			warmImage.srcset = thumbButton.dataset.fullSrcset;
		}
		warmImage.src = thumbButton.dataset.fullSrc;
	}

	function showPhoto(gallery, requestedIndex) {
		var total = gallery.thumbButtons.length;
		if (!total || !gallery.photo) return;

		var index = ((requestedIndex % total) + total) % total;
		var thumbButton = gallery.thumbButtons[index];
		var photo = gallery.photo;
		var caption = gallery.dialog.querySelector(".rgallery-modal__caption");
		var counter = gallery.dialog.querySelector(".rgallery-modal__count");

		gallery.dialog.dataset.photoIndex = index;

		photo.classList.add("is-loading");
		photo.onload = clearLoadingState;
		photo.onerror = clearLoadingState;
		photo.alt = thumbButton.dataset.fullAlt || "";

		// srcset before src, so the browser resolves a candidate rather than
		// fetching the fallback and then reconsidering.
		if (thumbButton.dataset.fullSrcset) {
			photo.srcset = thumbButton.dataset.fullSrcset;
		} else {
			photo.removeAttribute("srcset");
		}
		photo.src = thumbButton.dataset.fullSrc;
		thumbButton.dataset.preloaded = "1";

		// Re-showing the same photo doesn't change src, so no load event fires
		// and the loading class would stick.
		if (photo.complete) photo.classList.remove("is-loading");

		if (caption) caption.textContent = thumbButton.dataset.caption || "";
		if (counter) counter.textContent = (index + 1) + " / " + total;

		preloadPhoto(gallery.thumbButtons[(index + 1) % total], photo.sizes);
		preloadPhoto(gallery.thumbButtons[(index - 1 + total) % total], photo.sizes);
	}

	document.addEventListener("click", function (event) {
		if (!event.target.closest) return;

		var thumbButton = event.target.closest(".rgallery__btn");
		if (thumbButton) {
			var openingGallery = readGallery(thumbButton);
			if (!openingGallery) return;
			// Opened first: the counter is an aria-live region, and a polite
			// region that changes while still display:none is never announced.
			window.pyekModal.open(openingGallery.dialog);
			showPhoto(openingGallery, openingGallery.thumbButtons.indexOf(thumbButton));
			return;
		}

		var navButton = event.target.closest(".rgallery-modal__nav");
		if (navButton) {
			var openGallery = readGallery(navButton);
			if (!openGallery) return;
			showPhoto(
				openGallery,
				Number(openGallery.dialog.dataset.photoIndex) + Number(navButton.dataset.photoStep)
			);
		}
	});

	document.addEventListener("keydown", function (event) {
		if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;
		if (event.defaultPrevented || !event.target.closest) return;
		if (!event.target.closest(".rgallery-dialog[open]")) return;

		var gallery = readGallery(event.target);
		if (!gallery || gallery.thumbButtons.length < 2) return;

		// preventDefault, or the arrows scroll the dialog as well as advancing.
		event.preventDefault();
		showPhoto(
			gallery,
			Number(gallery.dialog.dataset.photoIndex) + (event.key === "ArrowRight" ? 1 : -1)
		);
	});

	/* Warm the full-size file on hover or keyboard focus, so the first open
	   isn't a blank frame. pointerover (not pointerenter) because only
	   bubbling events can be delegated; it fires on element crossings, not on
	   every mouse move. */
	function warmHoveredPhoto(event) {
		if (!event.target.closest) return;
		var thumbButton = event.target.closest(".rgallery__btn");
		if (!thumbButton || thumbButton.dataset.preloaded) return;

		var gallery = readGallery(thumbButton);
		preloadPhoto(thumbButton, gallery && gallery.photo ? gallery.photo.sizes : "");
	}

	document.addEventListener("pointerover", warmHoveredPhoto, { passive: true });
	document.addEventListener("focusin", warmHoveredPhoto);
})();
