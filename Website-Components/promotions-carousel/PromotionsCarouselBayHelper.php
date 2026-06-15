<?php
declare(strict_types=1);

/**
 * PromotionsCarouselBayHelper
 *
 * Pure-logic helper for the Bay location promotions carousel.
 * All data-transformation methods are WordPress-free and unit-testable.
 * Only renderCard() and render() depend on WP escape functions (esc_html,
 * esc_attr, esc_url), which are stubbed in tests/bootstrap.php.
 *
 * The companion template (promotions-carousel-bay.php) owns all WordPress I/O:
 * WP_Query, get_field(), get_the_title(), get_permalink(), echo.
 */
class PromotionsCarouselBayHelper
{
    // ─── Query ────────────────────────────────────────────────────────────────

    /**
     * Returns WP_Query args for Bay location promotions.
     * Update 'taxonomy' and 'terms' if your site uses a custom taxonomy.
     */
    public static function buildQueryArgs(): array
    {
        return [
            'post_type'      => 'promotion',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => [
                [
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => 'bay',
                ],
            ],
        ];
    }

    // ─── Date formatting ──────────────────────────────────────────────────────

    /**
     * Parses a raw ACF end-date string into a display label.
     *
     * ACF Date Picker stores dates in Ymd format (e.g. "20261231").
     * Returns ['label' => string, 'has_date' => bool].
     *
     * Returns 'Limited Time' for:
     *   - empty / whitespace-only strings
     *   - strings that don't match Ymd (e.g. "2026-12-31", "foobar")
     *   - overflow dates that PHP silently rolls over (e.g. Feb 30 → Mar 2)
     */
    public static function formatEndDate(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return ['label' => 'Limited Time', 'has_date' => false];
        }

        $dt = \DateTime::createFromFormat('Ymd', $raw);

        if ($dt === false) {
            return ['label' => 'Limited Time', 'has_date' => false];
        }

        // Catch overflow dates (e.g. Feb 30 rolls to Mar 2, triggering a warning).
        // getLastErrors() returns false in PHP 8.3+ when there are no errors,
        // so we guard with !== false before accessing the array.
        $errors = \DateTime::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return ['label' => 'Limited Time', 'has_date' => false];
        }

        return [
            'label'    => 'Ends – ' . $dt->format('F jS'),
            'has_date' => true,
        ];
    }

    // ─── Card building ────────────────────────────────────────────────────────

    /**
     * Builds a normalised card data array from pre-collected ACF values.
     *
     * Accepts raw ACF values, which return false (not null) for empty fields.
     * This method has no WordPress dependencies — the caller is responsible
     * for fetching fields via get_field() and passing them in $fields.
     *
     * @param array{
     *   promotion_images:   mixed,
     *   promotion_end_date: mixed,
     *   priority:           mixed,
     *   promotion_name:     mixed,
     *   promotion_price:    mixed,
     * } $fields          Raw ACF field values keyed by field name.
     * @param string $fallbackTitle  From WordPress get_the_title().
     * @param string $permalink      From WordPress get_permalink().
     *
     * @return array{
     *   title:      string,
     *   price:      string,
     *   img_url:    string,
     *   img_alt:    string,
     *   date_label: string,
     *   has_date:   bool,
     *   permalink:  string,
     *   priority:   int,
     * }
     */
    public static function buildCard(
        array  $fields,
        string $fallbackTitle,
        string $permalink
    ): array {
        // ACF returns false for empty fields; is_array() correctly rejects false/null.
        $images = is_array($fields['promotion_images'] ?? null)
            ? $fields['promotion_images']
            : [];

        // Use !== false so an empty string '' is preserved (won't trigger formatEndDate early).
        $endRaw = ($fields['promotion_end_date'] !== false && $fields['promotion_end_date'] !== null)
            ? (string) $fields['promotion_end_date']
            : '';

        // is_numeric() is used instead of ?: so priority 0 is valid (means "show first").
        // false ?: 999 would incorrectly override 0 to 999.
        $priority = is_numeric($fields['priority'] ?? null)
            ? (int) $fields['priority']
            : 999;

        // 4x3_image sub-field is configured as "Image URL" in ACF → plain string.
        $imgUrl = is_string($images['4x3_image'] ?? null) ? (string) $images['4x3_image'] : '';
        $imgAlt = is_string($images['alt_text'] ?? null) ? (string) $images['alt_text'] : $fallbackTitle;

        $dateInfo = static::formatEndDate($endRaw);

        // Use ACF promotion_name if it's a non-empty string; fall back to WP post title.
        $title = (is_string($fields['promotion_name'] ?? null) && $fields['promotion_name'] !== '')
            ? (string) $fields['promotion_name']
            : $fallbackTitle;

        // Price can be a formatted string ("$29.99") or numeric; treat false/null as empty.
        $price = ($fields['promotion_price'] !== false && $fields['promotion_price'] !== null)
            ? (string) $fields['promotion_price']
            : '';

        return [
            'title'      => $title,
            'price'      => $price,
            'img_url'    => $imgUrl,
            'img_alt'    => $imgAlt,
            'date_label' => $dateInfo['label'],
            'has_date'   => $dateInfo['has_date'],
            'permalink'  => $permalink,
            'priority'   => $priority,
        ];
    }

    // ─── Sorting ──────────────────────────────────────────────────────────────

    /**
     * Sorts cards by priority ascending (lower number = displayed first).
     * Returns a new sorted array; does not mutate the original.
     */
    public static function sortCards(array $cards): array
    {
        usort($cards, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return $cards;
    }

    /**
     * Returns true when there are enough cards to warrant navigation arrows.
     */
    public static function shouldShowArrows(array $cards): bool
    {
        return count($cards) > 1;
    }

    // ─── Rendering ────────────────────────────────────────────────────────────

    /**
     * Renders a single promotion card as an HTML string.
     * Depends on WordPress esc_html(), esc_attr(), esc_url().
     */
    public static function renderCard(array $card): string
    {
        $h = '<article class="cbv-card" role="listitem">';

        if ($card['img_url'] !== '') {
            $h .= '<div class="cbv-card__image-wrap">'
                . '<img class="cbv-card__image"'
                . ' src="' . esc_url($card['img_url']) . '"'
                . ' alt="' . esc_attr($card['img_alt']) . '"'
                . ' loading="lazy" decoding="async"'
                . '/>'
                . '</div>';
        }

        $h .= '<div class="cbv-card__wave">'
            . '<svg class="cbv-card__wave-shape" viewBox="0 0 400 36"'
            . ' preserveAspectRatio="none" aria-hidden="true">'
            . '<path d="M0,18 C80,36 160,0 240,18 C320,36 380,10 400,18 L400,36 L0,36 Z"'
            . ' fill="white"/>'
            . '</svg>';

        $h .= '<div class="cbv-card__content">'
            . '<h3 class="cbv-card__title">' . esc_html($card['title']) . '</h3>';

        if ($card['price'] !== '') {
            $h .= '<div class="cbv-card__price">' . esc_html($card['price']) . '</div>';
        }

        $dtAriaLabel = $card['has_date'] ? 'End date' : 'Duration';
        $h .= '<div class="cbv-card__dt-row" aria-label="' . esc_attr($dtAriaLabel) . '">';

        if ($card['has_date']) {
            $h .= '<svg class="cbv-card__icon" viewBox="0 0 24 24" fill="none"'
                . ' stroke="currentColor" stroke-width="1.75"'
                . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                . '<rect x="3" y="4" width="18" height="18" rx="2"/>'
                . '<path d="M16 2v4M8 2v4M3 10h18"/>'
                . '<path d="M9 16l2 2 4-4"/>'
                . '</svg>';
        } else {
            $h .= '<svg class="cbv-card__icon" viewBox="0 0 24 24" fill="none"'
                . ' stroke="currentColor" stroke-width="1.75"'
                . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                . '<circle cx="12" cy="12" r="10"/>'
                . '<path d="M12 6v6l4 2"/>'
                . '</svg>';
        }

        $h .= '<span class="cbv-card__date">' . esc_html($card['date_label']) . '</span>'
            . '</div>'; // dt-row

        $h .= '<a class="cbv-card__button"'
            . ' href="' . esc_url($card['permalink']) . '"'
            . ' aria-label="' . esc_attr('See details for ' . $card['title']) . '"'
            . '>See Details</a>';

        $h .= '</div>'; // content
        $h .= '</div>'; // wave
        $h .= '</article>';

        return $h;
    }

    /**
     * Renders the full carousel section.
     * Returns an empty string when $cards is empty.
     */
    public static function render(array $cards): string
    {
        if (empty($cards)) {
            return '';
        }

        $showArrows = static::shouldShowArrows($cards);
        $prevBtn = $nextBtn = '';

        if ($showArrows) {
            $prevSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
                . '<path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2"'
                . ' stroke-linecap="round" stroke-linejoin="round"/>'
                . '</svg>';
            $nextSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
                . '<path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2"'
                . ' stroke-linecap="round" stroke-linejoin="round"/>'
                . '</svg>';

            $prevBtn = '<button class="cbv-carousel__arrow cbv-carousel__arrow--prev"'
                . ' aria-label="Previous promotions" hidden>' . $prevSvg . '</button>';
            $nextBtn = '<button class="cbv-carousel__arrow cbv-carousel__arrow--next"'
                . ' aria-label="Next promotions" hidden>' . $nextSvg . '</button>';
        }

        $cardsHtml = implode('', array_map([static::class, 'renderCard'], $cards));

        return '<section class="cbv-carousel cbv-carousel--promotions" aria-label="Bay Promotions">'
            . '<div class="cbv-carousel__wrapper">'
            . $prevBtn
            . '<div class="cbv-carousel__track" role="list">'
            . $cardsHtml
            . '</div>'
            . $nextBtn
            . '</div>'
            . '<div class="cbv-carousel__dots"></div>'
            . '</section>';
    }
}
