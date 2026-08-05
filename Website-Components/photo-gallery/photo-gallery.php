<?php
/**
 * Oxygen Code Block — Photo Gallery (markup + PHP only) — v3
 *
 * ACF field:
 *   ride_gallery (Gallery, return format: Image Array)
 *
 * Paste this file into one Oxygen "Code Block" element (PHP mode).
 *
 * ENQUEUE, in this order:
 *   1. pyek-modal.css + pyek-modal.js  (../pyek-modal/ — once per page)
 *   2. photo-gallery.css + photo-gallery.js
 *
 * v3 changes:
 *   - The lightbox is a plain <dialog class="pyek-modal">; the scrim, card,
 *     header and close button now come from the shared shell, so this file and
 *     park-map.php produce byte-identical modal chrome.
 *   - No instance ids in the JS: everything is found with closest('.rgallery'),
 *     so any number of galleries on a page work with no counters, no registry
 *     and no re-init hook.
 *   - The dialog stays where it is rendered. A modal <dialog> lives in the top
 *     layer, which no ancestor's overflow or transform can clip, so the old
 *     "reparent to <body>" step (and the teardown code it needed) is gone.
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
		function ( $image ) {
			return is_array( $image ) && ! empty( $image['url'] );
		}
	)
);

if ( empty( $ride_gallery ) ) {
	return;
}

/**
 * Build a srcset from every registered size ACF reports, plus the original.
 *
 * Identical to the copy in park-map.php on purpose: an Oxygen Code Block has to
 * be self-contained, and the function_exists guard means whichever block
 * renders first defines it. Move it to the child theme's functions.php and both
 * guards will simply no-op.
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

/* Per-page instance counter, matching park-map.php's convention. It only exists
   to keep the aria-labelledby / aria-describedby ids unique; the JS never
   looks at it. */
$GLOBALS['rgallery_instance_count'] = isset( $GLOBALS['rgallery_instance_count'] )
	? (int) $GLOBALS['rgallery_instance_count'] + 1
	: 1;

$gallery_uid   = 'rgallery-' . (int) $GLOBALS['rgallery_instance_count'];
$photo_count   = count( $ride_gallery );
$page_title    = get_the_title();

/* The grid is a hero-plus-satellites layout that only tiles cleanly from five
   images up. Anything less needs its own column template or it renders with
   holes, so the count is exposed to CSS (capped at 5 = "the normal layout"). */
$layout_count = min( $photo_count, 5 );

/* Lightbox display width — matches the shared card, which is capped at 900px
   with the image running full-bleed inside it. */
$lightbox_sizes = '(max-width: 940px) 100vw, 900px';
?>

<div class="rgallery">

	<ul
		class="rgallery__grid"
		data-rgallery-count="<?php echo esc_attr( $layout_count ); ?>"
		role="list"
		aria-label="<?php echo esc_attr( $page_title ); ?> photo gallery"
	>
		<?php foreach ( $ride_gallery as $index => $image ) :

			$is_hero = ( 0 === $index );

			/* The hero spans two rows on desktop and the full width at tablet;
			   the satellites are a third of the grid at most. One shared `sizes`
			   string can't describe both. Both values run slightly generous —
			   the hero is a portrait crop on desktop, so `cover` needs more
			   width than the cell's CSS width implies.

			   Below 768px both become 78vw cells in the horizontal strip (see
			   photo-gallery.css). Leaving that entry at 100vw would have every
			   phone fetch a file about a third wider than it can display. */
			$thumb_sizes = $is_hero
				? '(max-width: 767px) 78vw, (max-width: 900px) 100vw, 60vw'
				: '(max-width: 767px) 78vw, (max-width: 900px) 50vw, 33vw';

			/* width/height must describe the file in `src`, not the original,
			   or the declared aspect ratio is wrong. */
			if ( ! empty( $image['sizes']['medium'] ) && ! empty( $image['sizes']['medium-width'] ) ) {
				$thumb_url    = $image['sizes']['medium'];
				$thumb_width  = (int) $image['sizes']['medium-width'];
				$thumb_height = (int) ( $image['sizes']['medium-height'] ?? 0 );
			} else {
				$thumb_url    = $image['url'];
				$thumb_width  = (int) ( $image['width'] ?? 0 );
				$thumb_height = (int) ( $image['height'] ?? 0 );
			}

			$full_srcset = pyek_image_srcset( $image );
			$alt_text    = ! empty( $image['alt'] ) ? $image['alt'] : $page_title . ' photo ' . ( $index + 1 );
			$caption     = ! empty( $image['caption'] ) ? $image['caption'] : '';
			$caption_id  = $gallery_uid . '-caption-' . $index;

			/* The index is always in the label. Editors routinely paste the same
			   alt text on every photo in a gallery, which would otherwise give
			   every button an identical accessible name. */
			$button_label = sprintf( 'View larger photo %d of %d: %s', $index + 1, $photo_count, $alt_text );
			?>
			<li class="rgallery__item">
				<button
					type="button"
					class="rgallery__btn"
					data-full-src="<?php echo esc_url( $image['url'] ); ?>"
					<?php if ( $full_srcset ) : ?>
					data-full-srcset="<?php echo esc_attr( $full_srcset ); ?>"
					<?php endif; ?>
					data-full-alt="<?php echo esc_attr( $alt_text ); ?>"
					<?php if ( $caption ) : ?>
					data-caption="<?php echo esc_attr( $caption ); ?>"
					aria-describedby="<?php echo esc_attr( $caption_id ); ?>"
					<?php endif; ?>
					aria-label="<?php echo esc_attr( $button_label ); ?>"
				>
					<img
						class="rgallery__img"
						src="<?php echo esc_url( $thumb_url ); ?>"
						<?php if ( $full_srcset ) : ?>
						srcset="<?php echo esc_attr( $full_srcset ); ?>"
						sizes="<?php echo esc_attr( $thumb_sizes ); ?>"
						<?php endif; ?>
						alt=""
						<?php if ( $thumb_width && $thumb_height ) : ?>
						width="<?php echo esc_attr( $thumb_width ); ?>"
						height="<?php echo esc_attr( $thumb_height ); ?>"
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
					<span class="rgallery__caption" id="<?php echo esc_attr( $caption_id ); ?>"><?php echo esc_html( $caption ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php /* The lightbox. Chrome comes from pyek-modal.css; only the body,
	         the prev/next buttons and the caption bar are this component's.
	         The image ships with no src — photo-gallery.js assigns it when a
	         thumbnail is clicked, so nothing full-size is fetched up front. */ ?>
	<dialog
		class="pyek-modal rgallery-dialog"
		aria-labelledby="<?php echo esc_attr( $gallery_uid ); ?>-title"
	>
		<div class="pyek-modal__card">

			<div class="pyek-modal__header">
				<span class="pyek-modal__title guttery" id="<?php echo esc_attr( $gallery_uid ); ?>-title">Photos</span>
				<div class="pyek-modal__header-end">
					<?php if ( $photo_count > 1 ) : ?>
						<span class="rgallery-modal__count" aria-live="polite"></span>
					<?php endif; ?>
					<button type="button" class="pyek-modal__close" data-modal-close aria-label="Close photo viewer" autofocus>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
						</svg>
					</button>
				</div>
			</div>

			<div class="pyek-modal__body rgallery-modal__body">
				<img
					class="rgallery-modal__img"
					alt=""
					sizes="<?php echo esc_attr( $lightbox_sizes ); ?>"
					decoding="async"
				/>

				<?php if ( $photo_count > 1 ) : ?>
					<button type="button" class="rgallery-modal__nav rgallery-modal__nav--prev" data-photo-step="-1" aria-label="Previous photo">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M15 18L9 12L15 6"/>
						</svg>
					</button>
					<button type="button" class="rgallery-modal__nav rgallery-modal__nav--next" data-photo-step="1" aria-label="Next photo">
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
