<?php
/**
 * Oxygen Code Block — Photo Gallery (markup + PHP only)  v2
 *
 * ACF field:
 *   ride_gallery (Gallery, return format: Image Array)
 *
 * Paste this file into one Oxygen "Code Block" element (PHP mode).
 * ENQUEUE: photo-gallery.css + photo-gallery.js (see sibling files in this
 * folder) — add photo-gallery.css to Oxygen's global stylesheet and
 * photo-gallery.js to its footer scripts, or wrap each in <style>/<script>
 * tags in their own Code Block on the same page.
 *
 * v2 notes:
 *   - Every instance carries data-rgallery-instance="{n}", so more than one
 *     gallery can live on a page (v1 only ever wired up the first).
 *   - srcset now spans every registered size *plus* the original upload, and
 *     the hero cell gets its own `sizes` — v1 told the browser every image
 *     was 260px wide, which is why the big desktop image came back grainy.
 *   - Captions live outside the <button> so screen readers actually receive
 *     them (an aria-label on the button suppresses its descendant text).
 */

if ( ! function_exists( 'get_field' ) ) {
	return;
}

$ride_gallery = get_field( 'ride_gallery' );

if ( empty( $ride_gallery ) || ! is_array( $ride_gallery ) ) {
	return;
}

/* Keep only rows that look like ACF's "Image Array" return format. If someone
   flips the field to Image ID / Image URL in the ACF UI — a one-click change
   for a non-developer — the gallery degrades to nothing instead of throwing
   "Trying to access array offset on value of type int" on every image. */
$ride_gallery = array_values(
	array_filter(
		$ride_gallery,
		function ( $img ) {
			return is_array( $img ) && ! empty( $img['url'] );
		}
	)
);

if ( empty( $ride_gallery ) ) {
	return;
}

if ( ! function_exists( 'rgallery_build_srcset' ) ) {
	/**
	 * Build a srcset from every registered size ACF reports, plus the original.
	 *
	 * v1 only offered medium / medium_large / large, so the widest candidate
	 * was ~1024px — not remotely enough for the hero cell, which is ~550 CSS px
	 * on desktop (so ~1100 device px at 2x) and full-bleed on mobile. Sweeping
	 * the whole sizes array picks up WordPress's 1536x1536 and 2048x2048 sizes
	 * automatically.
	 *
	 * Including $image['url'] is safe: WP 5.3+ downscales oversized uploads to
	 * the big-image threshold (2560px by default) and points the URL at the
	 * scaled file, so this can't drop a 6000px original into the candidate list.
	 *
	 * @param array $image ACF image array.
	 * @return string Comma-separated srcset, or '' if there's nothing to choose.
	 */
	function rgallery_build_srcset( $image ) {
		$by_width = [];

		if ( ! empty( $image['sizes'] ) && is_array( $image['sizes'] ) ) {
			foreach ( $image['sizes'] as $key => $url ) {
				// ACF stores "{size}", "{size}-width" and "{size}-height" as
				// siblings in one flat array — skip the dimension entries.
				if ( '-width' === substr( $key, -6 ) || '-height' === substr( $key, -7 ) ) {
					continue;
				}
				if ( empty( $url ) || ! is_string( $url ) ) {
					continue;
				}
				$w = isset( $image['sizes'][ $key . '-width' ] ) ? (int) $image['sizes'][ $key . '-width' ] : 0;
				if ( $w > 0 ) {
					// Keyed by width, so two sizes that resolve to the same
					// width (common with square crops) collapse to one.
					$by_width[ $w ] = $url;
				}
			}
		}

		if ( ! empty( $image['width'] ) ) {
			$by_width[ (int) $image['width'] ] = $image['url'];
		}

		// A single candidate is just `src` with extra steps.
		if ( count( $by_width ) < 2 ) {
			return '';
		}

		ksort( $by_width, SORT_NUMERIC );

		$parts = [];
		foreach ( $by_width as $w => $url ) {
			$parts[] = esc_url( $url ) . ' ' . (int) $w . 'w';
		}

		return implode( ', ', $parts );
	}
}

/* Per-page instance counter, matching park-map.php's convention. */
$GLOBALS['rgallery_instance_count'] = isset( $GLOBALS['rgallery_instance_count'] )
	? (int) $GLOBALS['rgallery_instance_count'] + 1
	: 1;

$rg_n     = (int) $GLOBALS['rgallery_instance_count'];
$rg_uid   = 'rgallery-' . $rg_n;
$rg_count = count( $ride_gallery );
$rg_name  = get_the_title();

/* The grid is a hero-plus-satellites layout that only tiles cleanly from five
   images up. Anything less needs its own column template or it renders with
   holes, so the count is exposed to CSS (capped at 5 = "the normal layout"). */
$rg_layout = min( $rg_count, 5 );

/* Lightbox display width — matches park-map.php: the modal card is capped at
   900px and the image runs full-bleed inside it. */
$rg_dialog_sizes = '(max-width: 940px) 100vw, 900px';
?>

<div class="rgallery" data-rgallery-instance="<?php echo esc_attr( $rg_n ); ?>">

	<ul
		class="rgallery__grid"
		data-rgallery-count="<?php echo esc_attr( $rg_layout ); ?>"
		role="list"
		aria-label="<?php echo esc_attr( $rg_name ); ?> photo gallery"
	>
		<?php foreach ( $ride_gallery as $i => $image ) :

			$is_hero = ( 0 === $i );

			/* The hero spans two rows / the full width on mobile; the satellites
			   are a third of the grid at most. One shared `sizes` string can't
			   describe both, which is the core of the graininess. Both values
			   run slightly generous — the hero is a portrait crop on desktop,
			   so `cover` needs more width than the cell's CSS width implies. */
			$sizes = $is_hero
				? '(max-width: 900px) 100vw, 60vw'
				: '(max-width: 640px) 100vw, (max-width: 900px) 50vw, 33vw';

			/* width/height must describe the file in `src`, not the original —
			   v1 mixed the two, so the declared aspect ratio was wrong. */
			if ( ! empty( $image['sizes']['medium'] ) && ! empty( $image['sizes']['medium-width'] ) ) {
				$thumb = $image['sizes']['medium'];
				$tw    = (int) $image['sizes']['medium-width'];
				$th    = isset( $image['sizes']['medium-height'] ) ? (int) $image['sizes']['medium-height'] : 0;
			} else {
				$thumb = $image['url'];
				$tw    = isset( $image['width'] ) ? (int) $image['width'] : 0;
				$th    = isset( $image['height'] ) ? (int) $image['height'] : 0;
			}

			/* Lightbox gets the original, not `large` — a 1024px file in a
			   900px-wide dialog is soft on any retina display. */
			$full    = $image['url'];
			$alt     = ! empty( $image['alt'] ) ? $image['alt'] : $rg_name . ' photo ' . ( $i + 1 );
			$caption = ! empty( $image['caption'] ) ? $image['caption'] : '';
			$srcset  = rgallery_build_srcset( $image );
			$cap_id  = 'rgallery-' . $rg_n . '-caption-' . $i;

			/* The index is always in the label. Editors routinely paste the
			   same alt text on every photo in a gallery, which would otherwise
			   give every button an identical accessible name. */
			$label = sprintf( 'View larger photo %d of %d: %s', $i + 1, $rg_count, $alt );
			?>
			<li class="rgallery__item">
				<button
					type="button"
					class="rgallery__btn"
					data-rgallery-full="<?php echo esc_url( $full ); ?>"
					<?php if ( $srcset ) : ?>
					data-rgallery-srcset="<?php echo esc_attr( $srcset ); ?>"
					<?php endif; ?>
					data-rgallery-alt="<?php echo esc_attr( $alt ); ?>"
					<?php if ( $caption ) : ?>
					data-rgallery-caption="<?php echo esc_attr( $caption ); ?>"
					aria-describedby="<?php echo esc_attr( $cap_id ); ?>"
					<?php endif; ?>
					aria-label="<?php echo esc_attr( $label ); ?>"
				>
					<img
						class="rgallery__img"
						src="<?php echo esc_url( $thumb ); ?>"
						<?php if ( $srcset ) : ?>
						srcset="<?php echo esc_attr( $srcset ); ?>"
						sizes="<?php echo esc_attr( $sizes ); ?>"
						<?php endif; ?>
						alt=""
						<?php if ( $tw && $th ) : ?>
						width="<?php echo esc_attr( $tw ); ?>"
						height="<?php echo esc_attr( $th ); ?>"
						<?php endif; ?>
						<?php if ( $is_hero ) : ?>
						loading="eager"
						fetchpriority="high"
						<?php else : ?>
						loading="lazy"
						<?php endif; ?>
						decoding="async"
					/>
				</button>
				<?php if ( $caption ) : ?>
					<?php /* Outside the button on purpose: aria-label on the
					         button overrides descendant text, so a caption
					         nested inside it is never announced. */ ?>
					<span class="rgallery__caption" id="<?php echo esc_attr( $cap_id ); ?>"><?php echo esc_html( $caption ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php /* Anatomy deliberately mirrors park-map.php so the two lightboxes read
	         as the same component: the <dialog> plays the part of
	         .pmap-modal-backdrop and .rgallery-modal is the white card.
	         Native <dialog> is kept for the free focus trap, Escape handling
	         and top-layer placement that the park-map backdrop hand-rolls.

	         Moved to <body> on init so it can't be clipped by an ancestor with
	         overflow / transform. No src: the lightbox image is only fetched
	         when the dialog is actually opened. */ ?>
	<dialog
		class="rgallery-dialog"
		data-rgallery-instance="<?php echo esc_attr( $rg_n ); ?>"
		aria-labelledby="<?php echo esc_attr( $rg_uid ); ?>-title"
	>
		<div class="rgallery-modal">

			<div class="rgallery-modal__header">
				<span class="rgallery-modal__title guttery" id="<?php echo esc_attr( $rg_uid ); ?>-title">Photos</span>
				<div class="rgallery-modal__header-end">
					<?php if ( $rg_count > 1 ) : ?>
						<span class="rgallery-modal__count" aria-live="polite"></span>
					<?php endif; ?>
					<button type="button" class="rgallery-modal__close" aria-label="Close photo viewer">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
						</svg>
					</button>
				</div>
			</div>

			<div class="rgallery-modal__body">
				<img
					class="rgallery-modal__img"
					alt=""
					sizes="<?php echo esc_attr( $rg_dialog_sizes ); ?>"
					decoding="async"
				/>

				<?php if ( $rg_count > 1 ) : ?>
					<button type="button" class="rgallery-modal__nav rgallery-modal__nav--prev" aria-label="Previous photo">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M15 18L9 12L15 6"/>
						</svg>
					</button>
					<button type="button" class="rgallery-modal__nav rgallery-modal__nav--next" aria-label="Next photo">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M9 18L15 12L9 6"/>
						</svg>
					</button>
				<?php endif; ?>
			</div>

			<p class="rgallery-modal__caption"></p>

		</div>
	</dialog>

</div>
