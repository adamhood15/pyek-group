<?php
/**
 * Events Carousel — Daytona Lagoon
 * CPT: events | No taxonomy filter — pulls all published events.
 * Enqueue: events-carousel.css + events-carousel.js
 */

$today = new DateTime( 'today' );

$events_dl_query = new WP_Query( [
	'post_type'      => 'events',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'date',
	'order'          => 'ASC',
] );

if ( ! $events_dl_query->have_posts() ) {
	wp_reset_postdata();
	return;
}

if ( ! function_exists( 'ec_resolve_date' ) ) {
	function ec_resolve_date( array $ts, DateTime $today ): array {
		$frequency = $ts['frequency'] ?? '';

		if ( $frequency === 'recurring' ) {
			$rows     = $ts['recurring_dates'] ?? [];
			$next_row = null;
			$next_dt  = null;

			foreach ( $rows as $row ) {
				$raw = $row['start_date'] ?? '';
				if ( ! $raw ) continue;
				try {
					$dt = new DateTime( $raw );
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
			return [
				'display' => ec_format_date_range( $next_dt, $next_row['end_date'] ?? '' ),
				'sort_dt' => $next_dt,
				'row'     => $next_row,
			];
		}

		$start_raw = $ts['start_date'] ?? '';
		if ( ! $start_raw ) {
			return [ 'display' => null, 'sort_dt' => null, 'row' => null ];
		}
		try {
			$start_dt = new DateTime( $start_raw );
		} catch ( Exception $e ) {
			return [ 'display' => null, 'sort_dt' => null, 'row' => null ];
		}
		return [
			'display' => ec_format_date_range( $start_dt, $ts['end_date'] ?? '' ),
			'sort_dt' => $start_dt,
			'row'     => null,
		];
	}
}

if ( ! function_exists( 'ec_format_date_range' ) ) {
	function ec_format_date_range( DateTime $start, string $end_raw ): string {
		if ( ! $end_raw ) return $start->format( 'F j, Y' );
		try {
			$end_dt = new DateTime( $end_raw );
		} catch ( Exception $e ) {
			return $start->format( 'F j, Y' );
		}
		if ( $start->format( 'Ym' ) === $end_dt->format( 'Ym' ) ) {
			return $start->format( 'F j' ) . '–' . $end_dt->format( 'j, Y' );
		}
		if ( $start->format( 'Y' ) === $end_dt->format( 'Y' ) ) {
			return $start->format( 'F j' ) . ' – ' . $end_dt->format( 'F j, Y' );
		}
		return $start->format( 'F j, Y' ) . ' – ' . $end_dt->format( 'F j, Y' );
	}
}

if ( ! function_exists( 'ec_resolve_time' ) ) {
	function ec_resolve_time( array $ts, ?array $recurring_row ): ?string {
		if ( ( $ts['need_time'] ?? 'no' ) !== 'yes' ) return null;
		if ( ( $ts['frequency'] ?? '' ) === 'recurring' && $recurring_row ) {
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
			if ( $end_fmt ) return $start_fmt . ' – ' . $end_fmt;
		}
		return $start_fmt;
	}
}

if ( ! function_exists( 'ec_format_time' ) ) {
	function ec_format_time( string $raw ): ?string {
		$raw = trim( $raw );
		if ( ! $raw ) return null;
		try {
			return ( new DateTime( $raw ) )->format( 'g:i A' );
		} catch ( Exception $e ) {
			return null;
		}
	}
}

// ─── Collect and sort cards ──────────────────────────────────────────────────

$dl_cards = [];

while ( $events_dl_query->have_posts() ) {
	$events_dl_query->the_post();

	$images = get_field( 'event_images' )     ?? [];
	$ts     = get_field( 'event_time_stamp' ) ?? [];

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

	$date_data = ec_resolve_date( $ts, $today );

	$dl_cards[] = [
		'title'     => get_field( 'event_name' ) ?: get_the_title(),
		'img_url'   => $img_url,
		'img_alt'   => $img_alt,
		'date'      => $date_data['display'],
		'time'      => ec_resolve_time( $ts, $date_data['row'] ),
		'permalink' => get_permalink(),
		'sort_dt'   => $date_data['sort_dt'],
	];
}

wp_reset_postdata();

if ( empty( $dl_cards ) ) return;

usort( $dl_cards, function ( $a, $b ) {
	if ( $a['sort_dt'] === null && $b['sort_dt'] === null ) return 0;
	if ( $a['sort_dt'] === null ) return 1;
	if ( $b['sort_dt'] === null ) return -1;
	return $a['sort_dt'] <=> $b['sort_dt'];
} );

$show_arrows = count( $dl_cards ) > 1;

?>

<section class="dl-carousel dl-carousel--events" aria-label="Daytona Lagoon Upcoming Events">
	<div class="dl-carousel__wrapper">
		<?php if ( $show_arrows ) : ?>
		<button class="dl-carousel__arrow dl-carousel__arrow--prev" aria-label="Previous events" hidden>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
				<path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>
		<?php endif; ?>
		<div class="dl-carousel__track" role="list">
			<?php foreach ( $dl_cards as $card ) : ?>
				<article class="dl-card" role="listitem">

					<?php if ( $card['img_url'] ) : ?>
						<div class="dl-card__image-wrap">
							<img
								class="dl-card__image"
								src="<?php echo esc_url( $card['img_url'] ); ?>"
								alt="<?php echo esc_attr( $card['img_alt'] ); ?>"
								loading="lazy"
								decoding="async"
							/>
						</div>
					<?php endif; ?>

					<div class="dl-card__wave">
						<svg class="dl-card__wave-shape" viewBox="0 0 400 36" preserveAspectRatio="none" aria-hidden="true">
							<path d="M0,18 C80,36 160,0 240,18 C320,36 380,10 400,18 L400,36 L0,36 Z" fill="white"/>
						</svg>

						<div class="dl-card__content">
							<h3 class="dl-card__title">
								<?php echo esc_html( $card['title'] ); ?>
							</h3>

							<?php if ( $card['date'] ) : ?>
								<div class="dl-card__dt-row" aria-label="Date">
									<svg class="dl-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<rect x="3" y="4" width="18" height="18" rx="2"/>
										<path d="M16 2v4M8 2v4M3 10h18"/>
										<path d="M9 16l2 2 4-4"/>
									</svg>
									<span class="dl-card__date"><?php echo esc_html( $card['date'] ); ?></span>
								</div>
							<?php endif; ?>

							<?php if ( $card['time'] ) : ?>
								<div class="dl-card__dt-row" aria-label="Time">
									<svg class="dl-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<circle cx="12" cy="12" r="10"/>
										<path d="M12 6v6l4 2"/>
									</svg>
									<span class="dl-card__time"><?php echo esc_html( $card['time'] ); ?></span>
								</div>
							<?php endif; ?>

							<a
								class="dl-card__button"
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
		<?php if ( $show_arrows ) : ?>
		<button class="dl-carousel__arrow dl-carousel__arrow--next" aria-label="Next events" hidden>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
				<path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>
		<?php endif; ?>
	</div>
	<div class="dl-carousel__dots"></div>
</section>