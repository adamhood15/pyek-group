<?php
/**
 * Promotions Carousel — ACF-powered, BEM-structured
 * CPT: promotions (update slug below if yours differs)
 * Field Group: PROMOTIONS
 *
 * Usage: include/require this file in a template, or paste into a PHP code block.
 *
 * COLOR THEMING
 * To change all colored elements (border, title, button) on a specific page,
 * override --sc-accent and --sc-accent-dark on the section element:
 *
 *   .promotions-carousel { --sc-accent: #ff6b6b; --sc-accent-dark: #8b0000; }
 *
 * Or inline: <section class="promotions-carousel" style="--sc-accent:#ff6b6b; --sc-accent-dark:#8b0000;">
 */

// NOTE: Do NOT use orderby=meta_value_num here — it silently excludes any post
// that doesn't have the 'priority' meta key set, returning zero results.
// Priority sorting is handled in PHP via usort() after all posts are fetched.
$promo_query = new WP_Query( [
	'post_type'      => 'promotion',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'date',
	'order'          => 'DESC',
] );

if ( ! $promo_query->have_posts() ) {
	wp_reset_postdata();
	return;
}

// Wrapped in function_exists so Austin and Houston variants can safely
// load on the same page without a PHP redeclaration fatal error.
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

$promo_cards = [];

while ( $promo_query->have_posts() ) {
	$promo_query->the_post();

	$images   = get_field( 'promotion_images' ) ?? [];
	$end_raw  = (string) ( get_field( 'promotion_end_date' ) ?? '' );
	$priority = (int) ( get_field( 'priority' ) ?? 999 );

	// 4x3_image return format is "Image URL" — comes back as a plain string.
	$img_url = is_string( $images['4x3_image'] ?? '' ) ? ( $images['4x3_image'] ?? '' ) : '';
	$img_alt = $images['alt_text'] ?? get_the_title();

	$date_info = pc_format_end_date( $end_raw );

	$promo_cards[] = [
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

if ( empty( $promo_cards ) ) return;

// Sort by priority ASC (lower number = shown first).
// WP_Query already handles this via meta_value_num, but sorting here covers
// any posts missing the priority field (they default to 999 above).
usort( $promo_cards, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );

?>

<section class="tt-carousel tt-carousel--promotions" aria-label="Current Promotions">
	<div class="tt-carousel__wrapper">
		<button class="tt-carousel__arrow tt-carousel__arrow--prev" aria-label="Previous promotions" hidden>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
				<path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>

		<div class="tt-carousel__track" role="list">
			<?php foreach ( $promo_cards as $card ) : ?>
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

		<button class="tt-carousel__arrow tt-carousel__arrow--next" aria-label="Next promotions" hidden>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
				<path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>
	</div>
</section>
