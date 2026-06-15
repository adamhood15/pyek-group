<?php
declare(strict_types=1);

/**
 * PromotionsCarouselHelper
 *
 * Single reusable helper for all location promotions carousels.
 * Install this class via a Code Snippets plugin (one snippet, all sites).
 *
 * Each Oxygen code block configures three values:
 *   $term          → taxonomy term slug  (e.g. 'bay', 'austin', 'houston', 'canyon')
 *   $locationLabel → display name        (e.g. 'Bay', 'Austin', 'Houston', 'Canyon')
 *   $cssPrefix     → BEM class prefix    ('cbv' for Bay/Canyon · 'tt' for Austin/Houston)
 */
class PromotionsCarouselHelper
{
    // ─── Query ────────────────────────────────────────────────────────────────

    /**
     * Returns WP_Query args filtered to a single location term.
     *
     * @param string $term     Taxonomy term slug (e.g. 'bay').
     * @param string $taxonomy Taxonomy slug. Default 'category'.
     */
    public static function buildQueryArgs( string $term, string $taxonomy = 'category' ): array
    {
        return [
            'post_type'      => 'promotion',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $term,
                ],
            ],
        ];
    }

    // ─── Date formatting ──────────────────────────────────────────────────────

    /**
     * Parses a raw ACF end-date string (F j, Y) into a display label.
     *
     * Returns ['label' => string, 'has_date' => bool].
     * Falls back to 'Limited Time' for empty, wrongly-formatted, or overflow dates.
     */
    public static function formatEndDate( string $raw ): array
    {
        $raw = trim( $raw );

        if ( $raw === '' ) {
            return [ 'label' => 'Limited Time', 'has_date' => false ];
        }

        $dt = \DateTime::createFromFormat( 'F j, Y', $raw );

        if ( $dt === false ) {
            return [ 'label' => 'Limited Time', 'has_date' => false ];
        }

        // Catch overflow dates (e.g. Feb 30 rolls to Mar 2, triggering a warning).
        // getLastErrors() returns false in PHP 8.3+ when clean, so guard with !== false.
        $errors = \DateTime::getLastErrors();
        if ( $errors !== false && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) {
            return [ 'label' => 'Limited Time', 'has_date' => false ];
        }

        return [
            'label'    => 'Ends – ' . $dt->format( 'F jS' ),
            'has_date' => true,
        ];
    }

    // ─── Card building ────────────────────────────────────────────────────────

    /**
     * Builds a normalised card array from raw ACF field values.
     *
     * ACF returns false (not null) for empty fields, so every field is
     * normalised here before the render methods touch it.
     *
     * @param array  $fields        Raw ACF values keyed by field name.
     * @param string $fallbackTitle From get_the_title().
     * @param string $permalink     From get_permalink().
     */
    public static function buildCard(
        array  $fields,
        string $fallbackTitle,
        string $permalink
    ): array {
        // is_array() rejects false/null without hiding a valid empty array.
        $images = is_array( $fields['promotion_images'] ?? null )
            ? $fields['promotion_images']
            : [];

        $endRaw = ( $fields['promotion_end_date'] !== false && $fields['promotion_end_date'] !== null )
            ? (string) $fields['promotion_end_date']
            : '';

        // is_numeric() so priority 0 is valid — using ?: would override 0 to 999.
        $priority = is_numeric( $fields['priority'] ?? null )
            ? (int) $fields['priority']
            : 999;

        $imgUrl = is_string( $images['4x3_image'] ?? null ) ? (string) $images['4x3_image'] : '';
        $imgAlt = is_string( $images['alt_text'] ?? null ) ? (string) $images['alt_text'] : $fallbackTitle;

        $dateInfo = static::formatEndDate( $endRaw );

        $title = ( is_string( $fields['promotion_name'] ?? null ) && $fields['promotion_name'] !== '' )
            ? (string) $fields['promotion_name']
            : $fallbackTitle;

        $price = ( $fields['promotion_price'] !== false && $fields['promotion_price'] !== null )
            ? (string) $fields['promotion_price']
            : '';

        $timeStamp   = is_array( $fields['event_time_stamp'] ?? null ) ? $fields['event_time_stamp'] : [];
        $frequency   = is_string( $timeStamp['frequency'] ?? null ) ? trim( $timeStamp['frequency'] ) : 'Single';
        $fallbackMsg = is_string( $timeStamp['date_fallback_message'] ?? null )
            ? trim( (string) $timeStamp['date_fallback_message'] )
            : '';
        $isRecurring = $frequency === 'Recurring';

        return [
            'title'        => $title,
            'price'        => $price,
            'img_url'      => $imgUrl,
            'img_alt'      => $imgAlt,
            'date_label'   => $dateInfo['label'],
            'has_date'     => $dateInfo['has_date'],
            'permalink'    => $permalink,
            'priority'     => $priority,
            'is_recurring' => $isRecurring,
            'fallback_msg' => $fallbackMsg,
            'show_date_row' => ! $isRecurring || $fallbackMsg !== '',
        ];
    }

    // ─── Sorting ──────────────────────────────────────────────────────────────

    /**
     * Sorts cards by priority ascending (lower number = displayed first).
     * Returns a new sorted array; does not mutate the original.
     */
    public static function sortCards( array $cards ): array
    {
        usort( $cards, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
        return $cards;
    }

    /**
     * Returns true when there are enough cards to warrant navigation arrows.
     */
    public static function shouldShowArrows( array $cards ): bool
    {
        return count( $cards ) > 1;
    }

    // ─── Rendering ────────────────────────────────────────────────────────────

    /**
     * Renders a single promotion card.
     *
     * @param array  $card      Hydrated card array from buildCard().
     * @param string $cssPrefix BEM prefix — 'cbv' or 'tt'.
     */
    public static function renderCard( array $card, string $cssPrefix ): string
    {
        $p = $cssPrefix;
        $h = '<article class="' . $p . '-card" role="listitem">';

        if ( $card['img_url'] !== '' ) {
            $h .= '<div class="' . $p . '-card__image-wrap">'
                . '<img class="' . $p . '-card__image"'
                . ' src="' . esc_url( $card['img_url'] ) . '"'
                . ' alt="' . esc_attr( $card['img_alt'] ) . '"'
                . ' loading="lazy" decoding="async"'
                . '/>'
                . '</div>';
        }

        $h .= '<div class="' . $p . '-card__wave">'
            . '<svg class="' . $p . '-card__wave-shape" viewBox="0 0 400 36"'
            . ' preserveAspectRatio="none" aria-hidden="true">'
            . '<path d="M0,18 C80,36 160,0 240,18 C320,36 380,10 400,18 L400,36 L0,36 Z"'
            . ' fill="white"/>'
            . '</svg>';

        $h .= '<div class="' . $p . '-card__content">'
            . '<h3 class="' . $p . '-card__title">' . esc_html( $card['title'] ) . '</h3>';

        if ( $card['price'] !== '' ) {
            $h .= '<div class="' . $p . '-card__price">' . esc_html( $card['price'] ) . '</div>';
        }

        if ( $card['show_date_row'] ) {
            $recurringFallback = $card['is_recurring'] && $card['fallback_msg'] !== '';

            if ( $recurringFallback ) {
                $dtAriaLabel = 'Schedule';
                $dtText      = $card['fallback_msg'];
                $dtIcon      = '<svg class="' . $p . '-card__icon" viewBox="0 0 24 24" fill="none"'
                    . ' stroke="currentColor" stroke-width="1.75"'
                    . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                    . '<rect x="3" y="4" width="18" height="18" rx="2"/>'
                    . '<path d="M16 2v4M8 2v4M3 10h18"/>'
                    . '<path d="M9 16l2 2 4-4"/>'
                    . '</svg>';
            } elseif ( $card['has_date'] ) {
                $dtAriaLabel = 'End date';
                $dtText      = $card['date_label'];
                $dtIcon      = '<svg class="' . $p . '-card__icon" viewBox="0 0 24 24" fill="none"'
                    . ' stroke="currentColor" stroke-width="1.75"'
                    . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                    . '<rect x="3" y="4" width="18" height="18" rx="2"/>'
                    . '<path d="M16 2v4M8 2v4M3 10h18"/>'
                    . '<path d="M9 16l2 2 4-4"/>'
                    . '</svg>';
            } else {
                $dtAriaLabel = 'Duration';
                $dtText      = $card['date_label'];
                $dtIcon      = '<svg class="' . $p . '-card__icon" viewBox="0 0 24 24" fill="none"'
                    . ' stroke="currentColor" stroke-width="1.75"'
                    . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                    . '<circle cx="12" cy="12" r="10"/>'
                    . '<path d="M12 6v6l4 2"/>'
                    . '</svg>';
            }

            $h .= '<div class="' . $p . '-card__dt-row" aria-label="' . esc_attr( $dtAriaLabel ) . '">'
                . $dtIcon
                . '<span class="' . $p . '-card__date">' . esc_html( $dtText ) . '</span>'
                . '</div>'; // dt-row
        }

        $h .= '<a class="' . $p . '-card__button"'
            . ' href="' . esc_url( $card['permalink'] ) . '"'
            . ' aria-label="' . esc_attr( 'See details for ' . $card['title'] ) . '"'
            . '>See Details</a>';

        $h .= '</div>'; // content
        $h .= '</div>'; // wave
        $h .= '</article>';

        return $h;
    }

    /**
     * Renders the full carousel section.
     *
     * @param array  $cards         Sorted, hydrated card array.
     * @param string $locationLabel Display name for aria-label (e.g. 'Bay').
     * @param string $cssPrefix     BEM prefix — 'cbv' or 'tt'.
     */
    public static function render( array $cards, string $locationLabel, string $cssPrefix ): string
    {
        if ( empty( $cards ) ) {
            return '';
        }

        $p           = $cssPrefix;
        $showArrows  = static::shouldShowArrows( $cards );
        $prevBtn     = $nextBtn = '';

        if ( $showArrows ) {
            $prevSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
                . '<path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2"'
                . ' stroke-linecap="round" stroke-linejoin="round"/>'
                . '</svg>';
            $nextSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
                . '<path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2"'
                . ' stroke-linecap="round" stroke-linejoin="round"/>'
                . '</svg>';

            $prevBtn = '<button class="' . $p . '-carousel__arrow ' . $p . '-carousel__arrow--prev"'
                . ' aria-label="Previous promotions" hidden>' . $prevSvg . '</button>';
            $nextBtn = '<button class="' . $p . '-carousel__arrow ' . $p . '-carousel__arrow--next"'
                . ' aria-label="Next promotions" hidden>' . $nextSvg . '</button>';
        }

        $cardsHtml = implode( '', array_map(
            fn( $card ) => static::renderCard( $card, $cssPrefix ),
            $cards
        ) );

        return '<section class="' . $p . '-carousel ' . $p . '-carousel--promotions"'
            . ' aria-label="' . esc_attr( $locationLabel . ' Promotions' ) . '">'
            . '<div class="' . $p . '-carousel__wrapper">'
            . $prevBtn
            . '<div class="' . $p . '-carousel__track" role="list">'
            . $cardsHtml
            . '</div>'
            . $nextBtn
            . '</div>'
            . '<div class="' . $p . '-carousel__dots"></div>'
            . '</section>';
    }
}
