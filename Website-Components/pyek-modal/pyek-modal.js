/* ==========================================================================
   PYEK Modal — shared behaviour for every <dialog class="pyek-modal">

   Paste into Oxygen's global JS (footer scripts), or into its own Code Block
   ONCE per page. Load order does not matter and there is nothing to
   initialise: every listener is delegated from `document`, so markup that
   arrives later (AJAX, a soft navigation, an Oxygen repeater) is wired up
   automatically.

   Markup contract:
     <button data-modal-open="dialog-id">      opens #dialog-id
     <button data-modal-close>                 closes its enclosing <dialog>
     A click on the scrim (the dialog's own padding) closes it.

   Focus trapping, focus restoration, Escape and background inertness all come
   free from <dialog>.showModal(). Page scroll locking does not — see below.

   Other components can open a modal directly with pyekModal.open(dialogEl).
   ========================================================================== */

(function () {
	"use strict";

	/* Reference counted, so two open modals (or a modal plus a nav drawer)
	   can't have the first one to close unlock the page for both. */
	var openModalCount = 0;

	/* Set on pointerdown, read on click. Pairing the two means a drag that
	   starts on the photo and releases over the scrim doesn't close the modal,
	   and neither does a click on the scrollbar. */
	var pressStartedOnScrim = false;

	function lockPageScroll() {
		if (openModalCount++ > 0) return;
		var scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
		document.documentElement.style.setProperty("--pyek-scrollbar-width", scrollbarWidth + "px");
		document.documentElement.classList.add("pyek-scroll-locked");
	}

	function unlockPageScroll() {
		if (openModalCount === 0 || --openModalCount > 0) return;
		document.documentElement.classList.remove("pyek-scroll-locked");
	}

	function openModal(dialog) {
		if (!dialog || dialog.open) return;
		dialog.showModal();
		lockPageScroll();
	}

	document.addEventListener("pointerdown", function (event) {
		pressStartedOnScrim = !!(event.target.matches && event.target.matches(".pyek-modal"));
	}, { passive: true });

	document.addEventListener("click", function (event) {
		var clicked = event.target;
		if (!clicked.closest) return;

		var openTrigger = clicked.closest("[data-modal-open]");
		if (openTrigger) {
			openModal(document.getElementById(openTrigger.dataset.modalOpen));
			return;
		}

		var closeTrigger = clicked.closest("[data-modal-close]");
		if (closeTrigger) {
			var owningDialog = closeTrigger.closest("dialog");
			if (owningDialog) owningDialog.close();
			return;
		}

		/* The dialog fills the viewport and the card sits inside it, so a
		   click whose target is the dialog itself landed on the scrim. */
		if (pressStartedOnScrim && clicked.matches(".pyek-modal")) clicked.close();
	});

	/* `close` does not bubble, so this listens in the capture phase. Escape,
	   the close button and a scrim click all unwind through here. */
	document.addEventListener("close", function (event) {
		if (event.target.matches && event.target.matches(".pyek-modal")) unlockPageScroll();
	}, true);

	window.pyekModal = { open: openModal };
})();
