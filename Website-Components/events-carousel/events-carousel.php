<?php
/**
 * Events Carousel — ACF-powered, BEM-structured
 * CPT: events | Field Group: EVENTS
 *
 * Usage: include/require this file in a template, or paste into a PHP code block.
 */

$today = new DateTime( 'today' );

$events_query = new WP_Query( [
	'post_type'      => 'events',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'date',
	'order'          => 'ASC',
] );

if ( ! $events_query->have_posts() ) {
	wp_reset_postdata();
	return; // Render nothing if no events exist.
}

/**
 * Resolve the display date string and the DateTime object used for sorting.
 * Returns an array: [ 'display' => string|null, 'sort_dt' => DateTime|null ]
 */
function ec_resolve_date( array $ts, DateTime $today ): array {
	$frequency = $ts['frequency'] ?? '';

	if ( $frequency === 'recurring' ) {
		$rows = $ts['recurring_dates'] ?? [];
		$next_row   = null;
		$next_dt    = null;

		foreach ( $rows as $row ) {
			$raw = $row['start_date'] ?? '';
			if ( ! $raw ) continue;

			try {
				$dt = new DateTime( $raw ); // format Ymd
			} catch ( Exception $e ) {
				continue;
			}

			if ( $dt >= $today ) {
				if ( $next_dt === null || $dt < $next_dt ) {
					$next_dt  = $dt;
					$next_row = $row;
				}
			}
		}

		if ( ! $next_dt || ! $next_row ) {
			return [ 'display' => null, 'sort_dt' => null, 'row' => null ];
		}

		$end_raw  = $next_row['end_date'] ?? '';
		$display  = ec_format_date_range( $next_dt, $end_raw );
		return [ 'display' => $display, 'sort_dt' => $next_dt, 'row' => $next_row ];
	}

	// Single
	$start_raw = $ts['start_date'] ?? '';
	$end_raw   = $ts['end_date']   ?? '';

	if ( ! $start_raw ) {
		return [ 'display' => null, 'sort_dt' => null, 'row' => null ];
	}

	try {
		$start_dt = new DateTime( $start_raw );
	} catch ( Exception $e ) {
		return [ 'display' => null, 'sort_dt' => null, 'row' => null ];
	}

	$display = ec_format_date_range( $start_dt, $end_raw );
	return [ 'display' => $display, 'sort_dt' => $start_dt, 'row' => null ];
}

/**
 * Build a human-readable date range string.
 * Same month+year → "July 24 – 26, 2026"
 * Different months  → "July 24 – August 2, 2026"
 * No end date       → "July 24, 2026"
 */
function ec_format_date_range( DateTime $start, string $end_raw ): string {
	if ( ! $end_raw ) {
		return $start->format( 'F j, Y' );
	}

	try {
		$end_dt = new DateTime( $end_raw );
	} catch ( Exception $e ) {
		return $start->format( 'F j, Y' );
	}

	if ( $start->format( 'Ym' ) === $end_dt->format( 'Ym' ) ) {
		// Same month and year
		return $start->format( 'F j' ) . '–' . $end_dt->format( 'j, Y' );
	}

	if ( $start->format( 'Y' ) === $end_dt->format( 'Y' ) ) {
		// Same year, different months
		return $start->format( 'F j' ) . ' – ' . $end_dt->format( 'F j, Y' );
	}

	// Different years
	return $start->format( 'F j, Y' ) . ' – ' . $end_dt->format( 'F j, Y' );
}

/**
 * Resolve display time string from the timestamp group + optional recurring row.
 * Returns string|null.
 */
function ec_resolve_time( array $ts, ?array $recurring_row ): ?string {
	$need_time = $ts['need_time'] ?? 'no';
	if ( $need_time !== 'yes' ) return null;

	$frequency = $ts['frequency'] ?? '';

	if ( $frequency === 'recurring' && $recurring_row ) {
		$start = $recurring_row['start_time'] ?? '';
		$end   = $recurring_row['end_time']   ?? '';
	} else {
		$start = $ts['start_time'] ?? '';
		$end   = $ts['end_time']   ?? '';
	}

	if ( ! $start ) return null;

	$start_fmt = ec_format_time( $start );
	if ( ! $start_fmt ) return null;

	if ( $end ) {
		$end_fmt = ec_format_time( $end );
		if ( $end_fmt ) {
			return $start_fmt . ' – ' . $end_fmt;
		}
	}

	return $start_fmt;
}

/**
 * Normalise a time string to "g:i A" display format.
 * Input format is "g:i a" (ACF default) — e.g. "7:30 pm".
 */
function ec_format_time( string $raw ): ?string {
	$raw = trim( $raw );
	if ( ! $raw ) return null;

	try {
		$dt = new DateTime( $raw );
		return $dt->format( 'g:i A' );
	} catch ( Exception $e ) {
		return null;
	}
}

// ─── Collect and sort cards ───────────────────────────────────────────────────

$cards = [];

while ( $events_query->have_posts() ) {
	$events_query->the_post();

	$post_id   = get_the_ID();
	$permalink = get_permalink();

	$images = get_field( 'event_images' )    ?? [];
	$ts     = get_field( 'event_time_stamp' ) ?? [];

	// ACF image fields can return a URL string, an array (Image Array format),
	// or an attachment ID (integer). Handle all three.
	$img_raw = $images['4x3_image'] ?? '';
	if ( is_array( $img_raw ) ) {
		$img_url = $img_raw['url'] ?? '';
		$img_alt = $images['alt_text'] ?: ( $img_raw['alt'] ?? get_the_title() );
	} elseif ( is_numeric( $img_raw ) && $img_raw > 0 ) {
		$img_url = wp_get_attachment_image_url( (int) $img_raw, 'full' )
	           ?: wp_get_attachment_url( (int) $img_raw )
	           ?: '';
		$img_alt = $images['alt_text'] ?: get_the_title();
	} else {
		$img_url = is_string( $img_raw ) ? $img_raw : '';
		$img_alt = $images['alt_text'] ?: get_the_title();
	}

	$date_data   = ec_resolve_date( $ts, $today );
	$time_string = ec_resolve_time( $ts, $date_data['row'] );

	$cards[] = [
		'title'     => get_field( 'event_name' ) ?: get_the_title(),
		'img_url'   => $img_url,
		'img_alt'   => $img_alt,
		'date'      => $date_data['display'],
		'time'      => $time_string,
		'permalink' => $permalink,
		'sort_dt'   => $date_data['sort_dt'],
	];
}

wp_reset_postdata();

if ( empty( $cards ) ) return;

// Sort ascending by sort_dt; undated events go to the end.
usort( $cards, function ( $a, $b ) {
	if ( $a['sort_dt'] === null && $b['sort_dt'] === null ) return 0;
	if ( $a['sort_dt'] === null ) return 1;
	if ( $b['sort_dt'] === null ) return -1;
	return $a['sort_dt'] <=> $b['sort_dt'];
} );

?>

<section class="tt-carousel tt-carousel--events" aria-label="Upcoming Events">
	<div class="tt-carousel__wrapper">
		<button class="tt-carousel__arrow tt-carousel__arrow--prev" aria-label="Previous events" hidden>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
				<path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>

		<div class="tt-carousel__track" role="list">
			<?php foreach ( $cards as $card ) : ?>
				<article class="tt-card" role="listitem">

					<?php if ( $card['img_url'] ) : ?>
						<div class="tt-card__image-wrap">
							<img
								class="tt-card__image"
								src="<?php echo esc_url( $card['img_url'] ); ?>"
								alt="<?php echo esc_attr( $card['img_alt'] ); ?>"
								loading="lazy"
								decoding="async"
							/>
						</div>
					<?php endif; ?>

					<div class="tt-card__wave">
						<svg class="tt-card__wave-shape" viewBox="0 0 400 36" preserveAspectRatio="none" aria-hidden="true">
							<path d="M0,18 C80,36 160,0 240,18 C320,36 380,10 400,18 L400,36 L0,36 Z" fill="white"/>
						</svg>

						<div class="tt-card__content">
							<h3 class="tt-card__title">
								<?php echo esc_html( $card['title'] ); ?>
							</h3>

							<?php if ( $card['date'] ) : ?>
								<div class="tt-card__dt-row" aria-label="Date">
									<svg class="tt-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<rect x="3" y="4" width="18" height="18" rx="2"/>
										<path d="M16 2v4M8 2v4M3 10h18"/>
										<path d="M9 16l2 2 4-4"/>
									</svg>
									<span class="tt-card__date"><?php echo esc_html( $card['date'] ); ?></span>
								</div>
							<?php endif; ?>

							<?php if ( $card['time'] ) : ?>
								<div class="tt-card__dt-row" aria-label="Time">
									<svg class="tt-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<circle cx="12" cy="12" r="10"/>
										<path d="M12 6v6l4 2"/>
									</svg>
									<span class="tt-card__time"><?php echo esc_html( $card['time'] ); ?></span>
								</div>
							<?php endif; ?>

							<a
								class="tt-card__button"
								href="<?php echo esc_url( $card['permalink'] ); ?>"
								aria-label="See details for <?php echo esc_attr( $card['title'] ); ?>"
							>
								See Details
							</a>
						</div>
					</div>

				</article>
			<?php endforeach; ?>
		</div>

		<button class="tt-carousel__arrow tt-carousel__arrow--next" aria-label="Next events" hidden>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
				<path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>
	</div>
</section>
