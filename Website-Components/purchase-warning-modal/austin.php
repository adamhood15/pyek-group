<?php
/**
 * Austin Ticket Warning Modal
 * Compatible with dynamically rendered React buttons.
 */
?>

<div id="austin-warning-modal" class="park-warning-modal" aria-hidden="true">
	<div
		class="park-warning-modal__content"
		role="dialog"
		aria-modal="true"
		aria-labelledby="austin-warning-title"
	>

		<button
			type="button"
			class="park-warning-modal__close"
			aria-label="Close modal"
		>
			&times;
		</button>

		<h3 id="austin-warning-title">
			This Ticket Is for Austin
		</h3>

		<p>
			You are about to purchase tickets for the Austin park location.
			If you are looking for Houston tickets instead, use the button below.
		</p>

		<div class="park-warning-modal__actions">

			<a
				href="<?php echo esc_url( home_url( '/houston/' ) ); ?>"
				class="park-warning-btn park-warning-btn--secondary"
			>
				Go to Houston
			</a>

			<button
				type="button"
				class="park-warning-btn park-warning-btn--primary"
				id="austin-warning-continue"
			>
				Continue
			</button>

		</div>

	</div>
</div>

<style>
.park-warning-modal {
	position: fixed;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px;
	background: rgba(0, 0, 0, 0.45);
	opacity: 0;
	visibility: hidden;
	transition: opacity 0.2s ease;
	z-index: 999999;
}

.park-warning-modal.is-active {
	opacity: 1;
	visibility: visible;
}

.park-warning-modal__content {
	position: relative;
	width: 100%;
	max-width: 420px;
	padding: 28px;
	border-radius: 14px;
	background: #ffffff;
	box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
	transform: translateY(10px);
	transition: transform 0.2s ease;
}

.park-warning-modal.is-active .park-warning-modal__content {
	transform: translateY(0);
}

.park-warning-modal__content h3 {
	margin: 0 0 12px;
	font-size: 24px;
	line-height: 1.2;
}

.park-warning-modal__content p {
	margin: 0 0 24px;
	line-height: 1.5;
	color: #555555;
}

.park-warning-modal__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
}

/**
 * Button Base
 */
.park-warning-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	text-decoration: none;
	cursor: pointer;
	border-radius: 100px;
	border: 2px solid transparent;
	font-weight: 700;
	line-height: 1;
	transition-duration: .2s;
	font-size: 1rem;
	text-align: center;
	text-transform: none;
	padding: 12px;
	flex: 1 1 auto;
}

.park-warning-btn--secondary {
	background: #ffffff;
	border-color: var(--primary);
	color: var(--primary);
}

.park-warning-btn--secondary:hover {
	background: var(--primary);
	border-color: var(--primary);
	color: #ffffff;
}

/**
 * Continue Button
 */
.park-warning-btn--primary {
	background: var(--primary);
	border-color: var(--primary);
	color: #ffffff;
}

.park-warning-btn--primary:hover {
	background: #ffffff;
	border-color: var(--primary);
	color: var(--primary);
}

.park-warning-modal__close {
	position: absolute;
	top: 10px;
	right: 12px;
	border: 0;
	background: transparent;
	font-size: 28px;
	line-height: 1;
	cursor: pointer;
	color: #666666;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

	const modal = document.getElementById('austin-warning-modal');
	const continueButton = document.getElementById('austin-warning-continue');
	const closeButton = modal.querySelector('.park-warning-modal__close');

	if (!modal || !continueButton || !closeButton) {
		return;
	}

	let activeCartButton = null;

	/**
	 * Open modal
	 */
	function openModal(button) {
		activeCartButton = button;

		modal.classList.add('is-active');
		modal.setAttribute('aria-hidden', 'false');
	}

	/**
	 * Close modal
	 */
	function closeModal() {
		modal.classList.remove('is-active');
		modal.setAttribute('aria-hidden', 'true');
	}

	/**
	 * Intercept dynamically rendered React buttons
	 */
	document.addEventListener('click', function(event) {

		const addToCartButton = event.target.closest('.btn.btn-info');

		if (!addToCartButton) {
			return;
		}

		/**
		 * Ignore modal buttons
		 */
		if (
			addToCartButton.id === 'austin-warning-continue' ||
			addToCartButton.closest('#austin-warning-modal')
		) {
			return;
		}

		/**
		 * Skip modal after confirmation
		 */
		if (addToCartButton.dataset.austinConfirmed === 'true') {
			addToCartButton.dataset.austinConfirmed = 'false';
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();

		openModal(addToCartButton);

	}, true);

	/**
	 * Continue purchase
	 */
	continueButton.addEventListener('click', function() {

		if (!activeCartButton) {
			return;
		}

		/**
		 * Prevent modal loop
		 */
		activeCartButton.dataset.austinConfirmed = 'true';

		closeModal();

		/**
		 * Re-trigger native click
		 */
		setTimeout(function() {

			activeCartButton.dispatchEvent(
				new MouseEvent('click', {
					bubbles: true,
					cancelable: true,
					view: window
				})
			);

		}, 100);

	});

	/**
	 * Close button
	 */
	closeButton.addEventListener('click', closeModal);

	/**
	 * Close on overlay click
	 */
	modal.addEventListener('click', function(event) {

		if (event.target === modal) {
			closeModal();
		}

	});

	/**
	 * ESC key support
	 */
	document.addEventListener('keydown', function(event) {

		if (event.key === 'Escape') {
			closeModal();
		}

	});

});
</script>