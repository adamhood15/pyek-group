<?php
/**
 * Promotions Carousel — Austin location
 * Pulls promotions assigned to the "Austin" category/taxonomy term.
 *
 * ─── TAXONOMY SETUP ────────────────────────────────────────────────────────
 * Update the two values marked ← below if needed:
 *   taxonomy  → the taxonomy slug (default: 'category'; change if you use a
 *               custom taxonomy such as 'location', 'city', 'region', etc.)
 *   terms     → the term SLUG, not the display name (find it in WP Admin >
 *               your taxonomy > hover the term to see its slug in the URL)
 * ───────────────────────────────────────────────────────────────────────────
 *
 * Enqueue: promotions-carousel.css + promotions-carousel.js
 */

$promo_austin_query = new WP_Query( [
	'post_type'      => 'promotion',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'tax_query'      => [
		[
			'taxonomy' => 'category', // ← update to your taxonomy slug
			'field'    => 'slug',
			'terms'    => 'austin',   // ← update to your exact term slug
		],
	],
] );

if ( ! $promo_austin_query->have_posts() ) {
	wp_reset_postdata();
	return;
}

if ( ! function_exists( 'pc_format_end_date' ) ) {
	function pc_format_end_date( string $raw ): array {
		$raw = trim( $raw );
		if ( ! $raw ) {
			return [ 'label' => 'Limited Time', 'has_date' => false ];
		}
		try {
			$dt = DateTime::createFromFormat( 'Ymd', $raw );
			if ( ! $dt ) { throw new Exception(); }
			return [ 'label' => 'Ends – ' . $dt->format( 'F jS' ), 'has_date' => true ];
		} catch ( Exception $e ) {
			return [ 'label' => 'Limited Time', 'has_date' => false ];
		}
	}
}

// ─── Collect cards ──────────────────────────────────────────────────────────

$promo_austin_cards = [];

while ( $promo_austin_query->have_posts() ) {
	$promo_austin_query->the_post();

	$images   = get_field( 'promotion_images' ) ?? [];
	$end_raw  = (string) ( get_field( 'promotion_end_date' ) ?? '' );
	$priority = (int) ( get_field( 'priority' ) ?? 999 );

	$img_url = is_string( $images['4x3_image'] ?? '' ) ? ( $images['4x3_image'] ?? '' ) : '';
	$img_alt = $images['alt_text'] ?? get_the_title();

	$date_info = pc_format_end_date( $end_raw );

	$promo_austin_cards[] = [
		'title'      => get_field( 'promotion_name' ) ?: get_the_title(),
		'price'      => (string) ( get_field( 'promotion_price' ) ?? '' ),
		'img_url'    => $img_url,
		'img_alt'    => $img_alt,
		'date_label' => $date_info['label'],
		'has_date'   => $date_info['has_date'],
		'permalink'  => get_permalink(),
		'priority'   => $priority,
	];
}

wp_reset_postdata();

if ( empty( $promo_austin_cards ) ) return;

usort( $promo_austin_cards, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );

// Show arrows only if more than one card exists
$show_arrows = count( $promo_austin_cards ) > 1;

?>

<section class="tt-carousel tt-carousel--promotions" aria-label="Austin Promotions">
	<div class="tt-carousel__wrapper">
		 <?php if ( $show_arrows ) : ?>
		<button class="tt-carousel__arrow tt-carousel__arrow--prev" aria-label="Previous promotions" hidden>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
				<path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>
		<?php endif; ?>

		<div class="tt-carousel__track" role="list">
			<?php foreach ( $promo_austin_cards as $card ) : ?>
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

							<?php if ( $card['price'] ) : ?>
								<div class="tt-card__price">
									<?php echo esc_html( $card['price'] ); ?>
								</div>
							<?php endif; ?>

							<div class="tt-card__dt-row" aria-label="<?php echo $card['has_date'] ? 'End date' : 'Duration'; ?>">
								<?php if ( $card['has_date'] ) : ?>
									<svg class="tt-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<rect x="3" y="4" width="18" height="18" rx="2"/>
										<path d="M16 2v4M8 2v4M3 10h18"/>
										<path d="M9 16l2 2 4-4"/>
									</svg>
								<?php else : ?>
									<svg class="tt-card__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<circle cx="12" cy="12" r="10"/>
										<path d="M12 6v6l4 2"/>
									</svg>
								<?php endif; ?>
								<span class="tt-card__date">
									<?php echo esc_html( $card['date_label'] ); ?>
								</span>
							</div>

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
		 <?php if ( $show_arrows ) : ?>
		<button class="tt-carousel__arrow tt-carousel__arrow--next" aria-label="Next promotions" hidden>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
				<path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>
		<?php endif; ?>
	</div>
	<div class="tt-carousel__dots"></div>
</section>
