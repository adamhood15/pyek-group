<?php
/**
 * Oxygen Code Block — Related Attractions (ap-related-card) — v1
 *
 * Renders up to three cards for attractions that share a taxonomy term with the
 * attraction being viewed. Markup and class names match section 9 of
 * Webpages/attraction-page-template-branded/, so the card visuals are the ones
 * already signed off in the template.
 *
 * Output is a single <div class="ap-related-grid"> and nothing else — the
 * surrounding section, the eyebrow label, the h2 and the sub-heading are all
 * Oxygen's. If the block is ever moved somewhere without a heading above it,
 * the grid needs an accessible name (see the note by the grid markup).
 *
 * When the cascade finds nothing, the grid is replaced by a single
 * .nz-button-blue link to the attractions listing, so the Oxygen heading above
 * is never left sitting over empty space. See $attractions_page_url — it is
 * location-specific.
 *
 * Taxonomies (tried in order, topping up until three cards are found):
 *   best-for  ->  thrill-level  ->  attraction-category  ->  recommended-age
 *
 * Card content:
 *   image        featured image of the related attraction
 *   thrill pill  thrill-level term + "Thrill", coloured by term slug
 *   title        the attraction's post title
 *   description  teaser_sentence (text field in the attraction ACF group)
 *   meta row     recommended-age term, plus the attraction-category term as a
 *                pill coloured per term slug
 *
 * best-for is not shown on the card, but it is still the first taxonomy in the
 * cascade above — what matches and what is displayed are set independently.
 *
 * Paste this file into one Oxygen "Code Block" element (PHP mode).
 * ENQUEUE: ap-related-card.css — Oxygen's global stylesheet, or a <style> tag
 * in its own Code Block on the same page.
 *
 * There is no ap-related-card.js. The thrill colour is a CSS class chosen in
 * PHP, which already knows the term at render time — so the pill is correct in
 * the first painted frame, costs nothing on the main thread, and still works
 * with JS disabled. Nothing else on the card is interactive.
 */

if ( ! function_exists( 'get_field' ) ) {
	return;
}

/* ══════════════════════════════════════════════════════════════════════════
   CONFIGURATION
   ══════════════════════════════════════════════════════════════════════════ */

$attraction_post_type = 'attractions';

/* ACF group that contains teaser_sentence. apr_group_field() reads the group
   first and falls back to a bare get_field('teaser_sentence'), which is what
   ACF stores when the group's "Prefix Field Names" toggle is off (the default)
   — so a wrong name here degrades rather than breaks. */
$attraction_field_group = 'attraction';

/* Priority order. This array is the single place the order is expressed —
   reorder it to reorder the priority, and the query cascade follows. */
$related_taxonomies = [ 'best-for', 'thrill-level', 'attraction-category', 'recommended-age' ];

$related_card_limit = 3;

/* The meta row shows recommended-age as an icon + text line and
   attraction-category as a coloured pill. Both are written out below rather than driven
   from a config array — two items with genuinely different shapes sharing one
   loop needs a style discriminator, which is more indirection than it saves.

   Category pill colours are keyed by term slug in ap-related-card.css. A term
   with no rule there gets the neutral slate default. */

/* Appended to the thrill term name on the pill, so a term called "High" reads
   as "High Thrill". Leave the terms themselves as the bare level or the pill
   ends up saying "High Thrill Thrill". */
$thrill_label_suffix = 'Thrill';

/* .ap-animate is opacity:0 until attraction-page.js adds .ap-animate--visible.
   Leave this false unless that script actually runs on the page, or the cards
   render permanently invisible. */
$use_scroll_animation = false;

/* Shown instead of the grid when the cascade finds nothing. This URL is
   LOCATION-SPECIFIC — an Austin or CBV copy of this block needs its own value,
   or Houston visitors' fallback will send Austin visitors to the wrong park.
   get_post_type_archive_link( $attraction_post_type ) resolves it automatically
   if the CPT has an archive enabled. */
$attractions_page_url = 'https://typhoontexas.com/houston/attractions/';
$fallback_button_text = 'View All Attractions';

/* ══════════════════════════════════════════════════════════════════════════
   HELPERS — all guarded, so a second Code Block on the page is harmless.
   ══════════════════════════════════════════════════════════════════════════ */

if ( ! function_exists( 'apr_term_ids' ) ) {
	/**
	 * Term ids a post has in one taxonomy.
	 *
	 * Returns [] rather than a WP_Error for an unregistered taxonomy, so a
	 * renamed or not-yet-created taxonomy quietly drops out of the cascade
	 * instead of taking the section down with it.
	 *
	 * @param int    $post_id  Post to read terms from.
	 * @param string $taxonomy Taxonomy slug.
	 * @return int[]
	 */
	function apr_term_ids( $post_id, $taxonomy ) {
		$term_ids = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
		return is_wp_error( $term_ids ) ? [] : $term_ids;
	}
}

if ( ! function_exists( 'apr_first_term' ) ) {
	/**
	 * The first term a post has in one taxonomy, or null.
	 *
	 * An attraction carries one thrill level / age / category in practice; if an
	 * editor adds a second, the card shows the first rather than overflowing the
	 * meta row. get_the_terms() reads the term cache the render query primed, so
	 * this costs no extra queries.
	 *
	 * @param int    $post_id  Post to read terms from.
	 * @param string $taxonomy Taxonomy slug.
	 * @return WP_Term|null
	 */
	function apr_first_term( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		return ( is_array( $terms ) && ! empty( $terms ) ) ? $terms[0] : null;
	}
}

if ( ! function_exists( 'apr_group_field' ) ) {
	/**
	 * A subfield of an ACF group, falling back to a top-level field of the same
	 * name.
	 *
	 * Reading the group is the reliable path: get_field('attraction') returns an
	 * array keyed by UNPREFIXED subfield names, whatever the group's settings.
	 *
	 * The fallback covers the two ways that can come up empty. A Group field has
	 * a "Prefix Field Names" toggle, off by default:
	 *   off (default)  subfields save to postmeta under their own name, so
	 *                  get_field('teaser_sentence') works on its own — and so
	 *                  does this fallback if $group_name is ever wrong.
	 *   on             subfields save as "attraction_teaser_sentence" and only
	 *                  the group read above returns anything.
	 * It also means the card survives the field being dragged out of the group
	 * later, which is a one-click change in the ACF UI.
	 *
	 * @param int    $post_id    Post to read from.
	 * @param string $group_name ACF group field name.
	 * @param string $field_name Subfield name.
	 * @return mixed
	 */
	function apr_group_field( $post_id, $group_name, $field_name ) {
		$group_values = get_field( $group_name, $post_id );

		if ( is_array( $group_values ) && ! empty( $group_values[ $field_name ] ) ) {
			return $group_values[ $field_name ];
		}

		return get_field( $field_name, $post_id );
	}
}

if ( ! function_exists( 'apr_find_related_ids' ) ) {
	/**
	 * Ids of up to $limit other attractions sharing a term with $post_id.
	 *
	 * Walks $taxonomies in order and tops up the result until it is full, so a
	 * best-for match is always preferred over a recommended-age one. Each pass
	 * excludes everything already found, which dedupes for free.
	 *
	 * At most one id-only query per taxonomy, and it stops the moment the limit
	 * is met — an attraction with three best-for siblings never runs the other
	 * three queries.
	 *
	 * @param int      $post_id    Attraction being viewed.
	 * @param string   $post_type  CPT slug to search.
	 * @param string[] $taxonomies Taxonomy slugs, highest priority first.
	 * @param int      $limit      Maximum number of ids to return.
	 * @return int[] Post ids, in priority order.
	 */
	function apr_find_related_ids( $post_id, $post_type, $taxonomies, $limit ) {
		$found_ids = [];

		foreach ( $taxonomies as $taxonomy ) {
			if ( count( $found_ids ) >= $limit ) {
				break;
			}

			$term_ids = apr_term_ids( $post_id, $taxonomy );
			if ( empty( $term_ids ) ) {
				continue;
			}

			$matches = new WP_Query( [
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit - count( $found_ids ),
				'post__not_in'           => array_merge( [ $post_id ], $found_ids ),
				/* Deterministic, so the same page caches the same three cards.
				   An undefined order would also make the top-up boundary move
				   between requests. */
				'orderby'                => 'menu_order title',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,   // nothing here paginates
				'update_post_meta_cache' => false,  // the render query primes these
				'update_post_term_cache' => false,
				'tax_query'              => [
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_ids,
						/* Exact terms only. On a hierarchical taxonomy, matching
						   children would let a broad parent pull in almost
						   everything and flatten the priority order. */
						'include_children' => false,
					],
				],
			] );

			$found_ids = array_merge( $found_ids, $matches->posts );
		}

		return $found_ids;
	}
}

if ( ! function_exists( 'apr_icon' ) ) {
	/**
	 * A 24x24 stroked icon from the same set as the rest of the attraction page.
	 *
	 * Always aria-hidden and focusable="false" — the adjacent text carries the
	 * meaning, and the false stops IE/legacy Edge putting SVGs in the tab order.
	 *
	 * Only two icons remain: the category now carries its meaning in a coloured
	 * pill instead. The per-category water icons and the best-for star are in
	 * git history if they are ever wanted back.
	 *
	 * @param string $name         bolt | users
	 * @param string $stroke_width SVG stroke-width.
	 * @return string Inline SVG, or '' for an unknown name.
	 */
	function apr_icon( $name, $stroke_width = '2' ) {
		$paths = [
			// Thrill pill.
			'bolt'  => '<polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
			// Recommended age.
			'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>'
				. '<path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
		];

		if ( empty( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'
			. esc_attr( $stroke_width )
			. '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. $paths[ $name ] . '</svg>';
	}
}

if ( ! function_exists( 'apr_render_fallback' ) ) {
	/**
	 * The "nothing related to show" state: one link to the full listing. The
	 * explanatory copy above it is already on the Oxygen page.
	 *
	 * An <a>, not a <button> — it navigates, so a button would give screen
	 * readers the wrong role and break open-in-new-tab. Only the theme class
	 * makes it look like a button.
	 *
	 * @param string $url  Attractions listing URL.
	 * @param string $text Visible link text.
	 * @return void
	 */
	function apr_render_fallback( $url, $text ) {
		if ( ! $url || ! $text ) {
			return;
		}
		?>
		<div class="ap-related-fallback">
			<a class="nz-button-blue" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $text ); ?></a>
		</div>
		<?php
	}
}

/* ══════════════════════════════════════════════════════════════════════════
   QUERY
   ══════════════════════════════════════════════════════════════════════════ */

$current_attraction_id = (int) get_the_ID();

/* No post context — the block dropped on an archive, a 404 or a non-attraction
   page — is treated the same as no matches, so the fallback below still gives
   the visitor somewhere to go. */
$related_ids = $current_attraction_id
	? apr_find_related_ids( $current_attraction_id, $attraction_post_type, $related_taxonomies, $related_card_limit )
	: [];

/* One render query for the whole set: it primes the post, term and meta caches
   in a single pass, so the loop below hits the database no further times.
   orderby post__in preserves the priority order built above. */
$related_query = empty( $related_ids ) ? null : new WP_Query( [
	'post_type'           => $attraction_post_type,
	'post_status'         => 'publish',
	'post__in'            => $related_ids,
	'orderby'             => 'post__in',
	'posts_per_page'      => count( $related_ids ),
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
] );

/* Single exit for every empty case, so the fallback has one call site. */
if ( ! $related_query || ! $related_query->have_posts() ) {
	wp_reset_postdata();
	apr_render_fallback( $attractions_page_url, $fallback_button_text );
	return;
}

$card_index = 0;
?>

<?php /* No aria-label / aria-labelledby here on purpose: Oxygen's h2 sits
         directly above and already names this group. Labelling the grid as well
         would announce the same words twice. If this block is ever placed
         without a heading above it, add aria-label="Related attractions". */ ?>
<div class="ap-related-grid">
	<?php
	while ( $related_query->have_posts() ) :
		$related_query->the_post();

		$related_id  = (int) get_the_ID();
		$thrill_term = apr_first_term( $related_id, 'thrill-level' );
		$teaser      = apr_group_field( $related_id, $attraction_field_group, 'teaser_sentence' );

		/* Both resolved up front so the meta row can be skipped entirely when an
		   attraction has neither — an empty bordered row reads as a bug. */
		$age_term      = apr_first_term( $related_id, 'recommended-age' );
		$category_term = apr_first_term( $related_id, 'attraction-category' );

		$card_classes = 'ap-related-card';
		if ( $use_scroll_animation ) {
			$card_classes .= ' ap-animate';
			if ( $card_index > 0 ) {
				$card_classes .= ' ap-animate--delay-' . $card_index;
			}
		}
		$card_index++;
		?>
		<a class="<?php echo esc_attr( $card_classes ); ?>" href="<?php echo esc_url( get_permalink( $related_id ) ); ?>">

			<div class="ap-related-card__img-wrap">
				<?php
				/* alt="" on purpose: the whole card is one link, and the title
				   sits directly beside the image. A described image would be read
				   out as part of the link's accessible name, ahead of the title
				   itself. */
				echo get_the_post_thumbnail( $related_id, 'medium_large', [
					'alt'      => '',
					'class'    => 'ap-related-card__img',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'sizes'    => '(max-width: 640px) 100vw, (max-width: 900px) 50vw, 33vw',
				] );
				?>

				<?php if ( $thrill_term ) : ?>
					<?php /* The suffix is visible text, so no screen-reader-only
					         counterpart is needed — "Extreme Thrill" already says
					         what the word measures. */ ?>
					<span class="ap-related-card__thrill ap-related-card__thrill--<?php echo esc_attr( $thrill_term->slug ); ?>">
						<?php echo apr_icon( 'bolt', '2.5' ); ?>
						<?php echo esc_html( trim( $thrill_term->name . ' ' . $thrill_label_suffix ) ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="ap-related-card__body">
				<h3 class="ap-related-card__title"><?php echo esc_html( get_the_title( $related_id ) ); ?></h3>

				<?php if ( $teaser ) : ?>
					<p class="ap-related-card__desc"><?php echo esc_html( $teaser ); ?></p>
				<?php endif; ?>

				<?php if ( $age_term || $category_term ) : ?>
					<div class="ap-related-card__meta">

						<?php if ( $age_term ) : ?>
							<div class="ap-related-card__meta-item">
								<?php echo apr_icon( 'users' ); ?>
								<?php /* An icon next to a bare "All Ages" doesn't say
								         what the pairing means, and there is no
								         visible label to lean on. */ ?>
								<span class="ap-sr-only">Recommended age: </span>
								<?php echo esc_html( $age_term->name ); ?>
							</div>
						<?php endif; ?>

						<?php if ( $category_term ) : ?>
							<?php /* Colour alone must never be the only carrier of
							         meaning (WCAG 1.4.1) — the pill always shows the
							         term name, the background just reinforces it. */ ?>
							<span class="ap-related-card__category ap-related-card__category--<?php echo esc_attr( $category_term->slug ); ?>">
								<span class="ap-sr-only">Category: </span>
								<?php echo esc_html( $category_term->name ); ?>
							</span>
						<?php endif; ?>

					</div>
				<?php endif; ?>
			</div>

		</a>
	<?php endwhile; ?>
</div>

<?php wp_reset_postdata(); ?>
