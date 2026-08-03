<?php
/**
 * Oxygen Code Block — Park Map (markup + PHP only) — v3
 *
 * ACF fields:
 *   map_section (group)
 *     map_image        (Image, return format: Image Array)
 *     map_description  (Text)
 *     map_pin_top      (Number — % from top of the map image)
 *     map_pin_left     (Number — % from left of the map image)
 *     nearby           (repeater)
 *       location (Text)
 *       page     (Page Link — returns permalink URL)
 *
 * Paste this file into one Oxygen "Code Block" element (PHP mode).
 *
 * ENQUEUE, in this order:
 *   1. pyek-modal.css + pyek-modal.js  (../pyek-modal/ — once per page)
 *   2. park-map.css
 * There is no park-map.js: the modal is a plain <dialog class="pyek-modal">
 * driven by the shared script's data-modal-open / data-modal-close hooks.
 *
 * v3 changes:
 *   - The hand-rolled backdrop, focus trap, scroll lock, inert toggling and
 *     instance registry are gone. A native modal <dialog> supplies all of it,
 *     and the shared script is ~60 lines of delegated listeners.
 *   - The old backdrop was position:fixed inset:0 z-index:999999 and lived in
 *     the DOM permanently, held back only by pointer-events:none. If the CSS
 *     ever failed to load it became an invisible sheet over the whole page,
 *     swallowing clicks on this component AND everything beside it. A closed
 *     <dialog> occupies no space at all.
 *   - The modal image carries a real src again, with loading="lazy". Inside a
 *     closed dialog it is never near the viewport, so it isn't fetched until
 *     the modal opens — same saving as the old data-attribute swap, no JS.
 */

if ( ! function_exists( 'get_field' ) ) {
	return;
}

$map_section = get_field( 'map_section' );
$map_image   = $map_section['map_image'] ?? null;

if ( empty( $map_image ) || ! is_array( $map_image ) || empty( $map_image['url'] ) ) {
	return;
}

/**
 * Build a srcset from every registered size ACF reports, plus the original.
 *
 * Identical to the copy in photo-gallery.php on purpose: an Oxygen Code Block
 * has to be self-contained, and the function_exists guard means whichever
 * block renders first defines it. Move it to the child theme's functions.php
 * and both guards will simply no-op.
 *
 * Including $image['url'] is safe: WP 5.3+ downscales oversized uploads to the
 * big-image threshold (2560px by default) and points the URL at the scaled
 * file, so this can't drop a 6000px original into the candidate list.
 *
 * @param array $image ACF image array.
 * @return string Comma-separated srcset, or '' if there's nothing to choose.
 */
if ( ! function_exists( 'pyek_image_srcset' ) ) {
	function pyek_image_srcset( $image ) {
		$url_by_width = [];

		if ( ! empty( $image['sizes'] ) && is_array( $image['sizes'] ) ) {
			foreach ( $image['sizes'] as $size_key => $size_url ) {
				// ACF stores "{size}", "{size}-width" and "{size}-height" as
				// siblings in one flat array — skip the dimension entries.
				if ( ! is_string( $size_url ) || empty( $size_url ) ) {
					continue;
				}
				if ( '-width' === substr( $size_key, -6 ) || '-height' === substr( $size_key, -7 ) ) {
					continue;
				}
				$width = (int) ( $image['sizes'][ $size_key . '-width' ] ?? 0 );
				if ( $width > 0 ) {
					// Keyed by width, so two sizes that resolve to the same
					// width (common with square crops) collapse to one.
					$url_by_width[ $width ] = $size_url;
				}
			}
		}

		if ( ! empty( $image['width'] ) ) {
			$url_by_width[ (int) $image['width'] ] = $image['url'];
		}

		// A single candidate is just `src` with extra steps.
		if ( count( $url_by_width ) < 2 ) {
			return '';
		}

		ksort( $url_by_width, SORT_NUMERIC );

		$candidates = [];
		foreach ( $url_by_width as $width => $url ) {
			$candidates[] = esc_url( $url ) . ' ' . (int) $width . 'w';
		}

		return implode( ', ', $candidates );
	}
}

/* Per-request instance counter — keeps ids unique if the block appears more
   than once on a page (e.g. inside an Oxygen repeater). */
$GLOBALS['pmap_instance_count'] = isset( $GLOBALS['pmap_instance_count'] )
	? (int) $GLOBALS['pmap_instance_count'] + 1
	: 1;

$pmap_number    = (int) $GLOBALS['pmap_instance_count'];
$pmap_uid       = 'pmap-' . $pmap_number;
$pmap_anchor    = ( 1 === $pmap_number ) ? 'park-map' : 'park-map-' . $pmap_number; // keeps existing #park-map links working
$pmap_dialog_id = $pmap_uid . '-dialog';

$attraction_name = get_the_title();
$pin_top         = ( isset( $map_section['map_pin_top'] ) && '' !== $map_section['map_pin_top'] ) ? (float) $map_section['map_pin_top'] : 50;
$pin_left        = ( isset( $map_section['map_pin_left'] ) && '' !== $map_section['map_pin_left'] ) ? (float) $map_section['map_pin_left'] : 50;
$map_srcset      = pyek_image_srcset( $map_image );
$map_alt         = ! empty( $map_image['alt'] ) ? $map_image['alt'] : 'Park map showing the ' . $attraction_name . ' location';
$nearby          = $map_section['nearby'] ?? null;
?>

<div class="pmap" id="<?php echo esc_attr( $pmap_anchor ); ?>">

	<div class="pmap__frame">
		<img
			class="pmap__img"
			src="<?php echo esc_url( $map_image['url'] ); ?>"
			<?php if ( $map_srcset ) : ?>
			srcset="<?php echo esc_attr( $map_srcset ); ?>"
			sizes="100vw"
			<?php endif; ?>
			alt="<?php echo esc_attr( $map_alt ); ?>"
			width="<?php echo esc_attr( $map_image['width'] ); ?>"
			height="<?php echo esc_attr( $map_image['height'] ); ?>"
			loading="lazy"
			decoding="async"
		/>

		<div class="pmap__pin-wrap" style="top:<?php echo esc_attr( $pin_top ); ?>%;left:<?php echo esc_attr( $pin_left ); ?>%;" aria-hidden="true">
			<span class="pmap__pin-tip"><?php echo esc_html( $attraction_name ); ?></span>
			<span class="pmap__pin"></span>
		</div>
	</div>

	<button
		type="button"
		class="pmap__expand"
		aria-haspopup="dialog"
		data-modal-open="<?php echo esc_attr( $pmap_dialog_id ); ?>"
	>
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/>
			<line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>
		</svg>
		View full park map
	</button>

	<?php if ( ! empty( $nearby ) && is_array( $nearby ) ) : ?>
		<div class="pmap__nearby" aria-label="Nearby attractions">
			<span class="pmap__nearby-label">Nearby:</span>
			<?php foreach ( $nearby as $row ) :
				$location = $row['location'] ?? '';
				$page_url = $row['page'] ?? '';
				if ( ! $location || ! $page_url ) {
					continue;
				}
				?>
				<a class="pmap__nearby-chip" href="<?php echo esc_url( $page_url ); ?>">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M9 18L15 12L9 6"/>
					</svg>
					<?php echo esc_html( $location ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php /* role="dialog" / aria-modal are implied by showModal() — declaring
	         them by hand is redundant and, on some AT, actively worse.
	         autofocus is what native <dialog> looks for when deciding where to
	         put the keyboard on open. */ ?>
	<dialog
		class="pyek-modal pmap-modal"
		id="<?php echo esc_attr( $pmap_dialog_id ); ?>"
		aria-labelledby="<?php echo esc_attr( $pmap_uid ); ?>-title"
	>
		<div class="pyek-modal__card">

			<div class="pyek-modal__header">
				<span class="pyek-modal__title guttery" id="<?php echo esc_attr( $pmap_uid ); ?>-title">Park Map</span>
				<div class="pyek-modal__header-end">
					<button type="button" class="pyek-modal__close" data-modal-close aria-label="Close map" autofocus>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
						</svg>
					</button>
				</div>
			</div>

			<div class="pyek-modal__body pmap-modal__body">
				<img
					class="pmap-modal__img"
					src="<?php echo esc_url( $map_image['url'] ); ?>"
					<?php if ( $map_srcset ) : ?>
					srcset="<?php echo esc_attr( $map_srcset ); ?>"
					sizes="(max-width: 940px) 100vw, 900px"
					<?php endif; ?>
					alt="<?php echo esc_attr( $map_alt ); ?>"
					width="<?php echo esc_attr( $map_image['width'] ); ?>"
					height="<?php echo esc_attr( $map_image['height'] ); ?>"
					loading="lazy"
					decoding="async"
				/>
			</div>

		</div>
	</dialog>

</div>
