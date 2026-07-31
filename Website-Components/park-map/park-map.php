<?php
/**
 * Oxygen Code Block — Park Map (markup + PHP only) — v2
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
 * ENQUEUE: park-map.css + park-map.js (see sibling files in this folder) —
 * add park-map.css to Oxygen's global stylesheet and park-map.js to its
 * footer scripts, or wrap each in <style>/<script> tags in their own
 * Code Block on the same page.
 *
 * v2 changes:
 *   - Each instance gets a number; trigger and backdrop are paired by it, so
 *     two maps on one page both work. The wrapper id is unique too.
 *   - role/aria-modal/aria-labelledby moved off the click-to-close backdrop
 *     and onto the actual dialog box.
 *   - Closed state uses `inert` instead of `aria-hidden` (the JS toggles it;
 *     the CSS adds visibility:hidden for browsers without inert support).
 *   - The modal image ships with no src — park-map.js assigns it on hover or
 *     first open — so the full-size map is no longer downloaded on every
 *     page load alongside the srcset copy in the frame.
 */

if ( ! function_exists( 'get_field' ) ) {
	return;
}

$map_section     = get_field( 'map_section' );
$map_image       = $map_section['map_image'] ?? null;
$pin_top         = ( isset( $map_section['map_pin_top'] ) && $map_section['map_pin_top'] !== '' ) ? (float) $map_section['map_pin_top'] : 50;
$pin_left        = ( isset( $map_section['map_pin_left'] ) && $map_section['map_pin_left'] !== '' ) ? (float) $map_section['map_pin_left'] : 50;
$attraction_name = get_the_title();

/* Per-request instance counter — keeps ids unique if the block appears more
   than once on a page (e.g. inside an Oxygen repeater). */
$GLOBALS['pmap_instance_count'] = isset( $GLOBALS['pmap_instance_count'] ) ? (int) $GLOBALS['pmap_instance_count'] + 1 : 1;
$pmap_n      = (int) $GLOBALS['pmap_instance_count'];
$pmap_uid    = 'pmap-' . $pmap_n;
$pmap_anchor = ( 1 === $pmap_n ) ? 'park-map' : 'park-map-' . $pmap_n; // keeps existing #park-map links working

if ( ! function_exists( 'pmap_build_srcset' ) ) {
	function pmap_build_srcset( $image ) {
		$by_width = [];

		if ( ! empty( $image['sizes'] ) ) {
			foreach ( [ 'medium', 'medium_large', 'large' ] as $size ) {
				if ( ! empty( $image['sizes'][ $size ] ) && ! empty( $image['sizes'][ $size . '-width' ] ) ) {
					$w              = (int) $image['sizes'][ $size . '-width' ];
					$by_width[ $w ] = esc_url( $image['sizes'][ $size ] ) . ' ' . $w . 'w';
				}
			}
		}

		// Include the original so the modal has a candidate large enough for
		// a 900px box on a 2x screen. Keyed by width, so a "large" that is
		// the original doesn't get listed twice.
		if ( ! empty( $image['url'] ) && ! empty( $image['width'] ) ) {
			$w              = (int) $image['width'];
			$by_width[ $w ] = esc_url( $image['url'] ) . ' ' . $w . 'w';
		}

		ksort( $by_width );
		return implode( ', ', $by_width );
	}
}

$pmap_srcset = $map_image ? pmap_build_srcset( $map_image ) : '';
$nearby      = $map_section['nearby'] ?? null;
$pmap_ratio  = ( ! empty( $map_image['width'] ) && ! empty( $map_image['height'] ) )
	? (int) $map_image['width'] . ' / ' . (int) $map_image['height']
	: '';
?>

<?php if ( $map_image ) : ?>
	<div class="pmap" id="<?php echo esc_attr( $pmap_anchor ); ?>">

		<div class="pmap__frame">
			<img
				class="pmap__img"
				src="<?php echo esc_url( $map_image['url'] ); ?>"
				<?php if ( $pmap_srcset ) : ?>
				srcset="<?php echo $pmap_srcset; ?>"
				sizes="100vw"
				<?php endif; ?>
				alt="<?php echo esc_attr( $map_image['alt'] ? $map_image['alt'] : 'Park map showing the ' . $attraction_name . ' location' ); ?>"
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
			data-pmap-map-trigger="<?php echo esc_attr( $pmap_n ); ?>"
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

	</div>

	<div class="pmap-modal-backdrop" data-pmap-instance="<?php echo esc_attr( $pmap_n ); ?>" inert>
		<div class="pmap-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $pmap_uid ); ?>-title">
			<div class="pmap-modal__header">
				<span class="pmap-modal__title guttery" id="<?php echo esc_attr( $pmap_uid ); ?>-title">Park Map</span>
				<button type="button" class="pmap-modal__close" aria-label="Close map">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
					</svg>
				</button>
			</div>
			<div class="pmap-modal__body">
				<img
					class="pmap-modal__img"
					alt="<?php echo esc_attr( $map_image['alt'] ? $map_image['alt'] : 'Full park map' ); ?>"
					width="<?php echo esc_attr( $map_image['width'] ); ?>"
					height="<?php echo esc_attr( $map_image['height'] ); ?>"
					<?php if ( $pmap_ratio ) : ?>style="aspect-ratio:<?php echo esc_attr( $pmap_ratio ); ?>;"<?php endif; ?>
					data-pmap-src="<?php echo esc_url( $map_image['url'] ); ?>"
					<?php if ( $pmap_srcset ) : ?>
					data-pmap-srcset="<?php echo esc_attr( $pmap_srcset ); ?>"
					sizes="(max-width: 940px) 100vw, 900px"
					<?php endif; ?>
					decoding="async"
				/>
			</div>
		</div>
	</div>
<?php endif; ?>