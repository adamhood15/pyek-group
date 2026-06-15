<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PromotionsCarouselHelper;

/**
 * Integration tests for PromotionsCarouselHelper::render() and renderCard()
 *
 * Tests run against both CSS prefixes ('cbv' and 'tt') to ensure the reusable
 * helper generates correct output for all four locations:
 *   Bay    → term:'bay',     prefix:'cbv', label:'Bay'
 *   Canyon → term:'canyon',  prefix:'cbv', label:'Canyon'
 *   Houston→ term:'houston', prefix:'tt',  label:'Houston'
 *   Austin → term:'austin',  prefix:'tt',  label:'Austin'
 */
final class CarouselRenderTest extends TestCase
{
    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function makeCard( array $overrides = [] ): array
    {
        return array_merge( [
            'title'      => 'Family Fun Pack',
            'price'      => '$39.99',
            'img_url'    => 'https://example.com/img/family-pack.jpg',
            'img_alt'    => 'Family Fun Pack banner',
            'date_label' => 'Ends – July 4th',
            'has_date'   => true,
            'permalink'  => 'https://example.com/promotions/family-fun-pack/',
            'priority'   => 1,
        ], $overrides );
    }

    // ── buildQueryArgs ────────────────────────────────────────────────────────

    /**
     * WHY: Confirms each location's query uses the correct term slug so the
     * WP_Query actually filters to that location's promotions.
     */
    #[Test]
    #[DataProvider( 'locationTermProvider' )]
    public function buildQueryArgsContainsCorrectTerm( string $term ): void
    {
        $args = PromotionsCarouselHelper::buildQueryArgs( $term );

        $this->assertSame( $term, $args['tax_query'][0]['terms'] );
        $this->assertSame( 'promotion', $args['post_type'] );
        $this->assertSame( 'category', $args['tax_query'][0]['taxonomy'] );
    }

    public static function locationTermProvider(): array
    {
        return [
            'bay'     => [ 'bay' ],
            'canyon'  => [ 'canyon' ],
            'houston' => [ 'houston' ],
            'austin'  => [ 'austin' ],
        ];
    }

    /**
     * WHY: Confirms a custom taxonomy slug can override the default 'category'
     * when the site uses a dedicated location taxonomy.
     */
    #[Test]
    public function buildQueryArgsAcceptsCustomTaxonomy(): void
    {
        $args = PromotionsCarouselHelper::buildQueryArgs( 'bay', 'location' );

        $this->assertSame( 'location', $args['tax_query'][0]['taxonomy'] );
    }

    // ── render() — section structure ─────────────────────────────────────────

    /**
     * WHY: Each site uses a different CSS prefix. The section element must use
     * the prefix passed in — wrong prefix would break all CSS and JS selectors.
     */
    #[Test]
    #[DataProvider( 'prefixLabelProvider' )]
    public function renderUsesCorrectCssPrefixOnSection( string $prefix, string $label ): void
    {
        $html = PromotionsCarouselHelper::render( [ $this->makeCard() ], $label, $prefix );

        $this->assertStringContainsString(
            'class="' . $prefix . '-carousel ' . $prefix . '-carousel--promotions"',
            $html
        );
    }

    /**
     * WHY: The ARIA label includes the location name so screen reader users
     * know which site's promotions they're browsing.
     */
    #[Test]
    #[DataProvider( 'prefixLabelProvider' )]
    public function renderUsesCorrectAriaLabel( string $prefix, string $label ): void
    {
        $html = PromotionsCarouselHelper::render( [ $this->makeCard() ], $label, $prefix );

        $this->assertStringContainsString(
            'aria-label="' . $label . ' Promotions"',
            $html
        );
    }

    public static function prefixLabelProvider(): array
    {
        return [
            'cbv / Bay'      => [ 'cbv', 'Bay' ],
            'cbv / Canyon'   => [ 'cbv', 'Canyon' ],
            'tt / Houston'   => [ 'tt',  'Houston' ],
            'tt / Austin'    => [ 'tt',  'Austin' ],
        ];
    }

    /**
     * WHY: The track and dots containers must also use the correct prefix,
     * since the JS initialises them by class selector.
     */
    #[Test]
    public function renderTrackAndDotsUsePrefix(): void
    {
        $html = PromotionsCarouselHelper::render( [ $this->makeCard() ], 'Bay', 'cbv' );

        $this->assertStringContainsString( 'class="cbv-carousel__track"', $html );
        $this->assertStringContainsString( 'class="cbv-carousel__dots"', $html );
    }

    // ── render() — arrow conditional logic ───────────────────────────────────

    /**
     * WHY: Arrow buttons must NOT appear for a single card.
     */
    #[Test]
    public function singleCardDoesNotRenderArrowButtons(): void
    {
        $html = PromotionsCarouselHelper::render( [ $this->makeCard() ], 'Bay', 'cbv' );

        $this->assertStringNotContainsString( 'carousel__arrow', $html );
    }

    /**
     * WHY: Two or more cards must render arrows with the correct prefix.
     */
    #[Test]
    #[DataProvider( 'prefixLabelProvider' )]
    public function twoCardsRenderArrowsWithCorrectPrefix( string $prefix, string $label ): void
    {
        $html = PromotionsCarouselHelper::render(
            [ $this->makeCard(), $this->makeCard( [ 'title' => 'Card 2' ] ) ],
            $label,
            $prefix
        );

        $this->assertStringContainsString( $prefix . '-carousel__arrow--prev', $html );
        $this->assertStringContainsString( $prefix . '-carousel__arrow--next', $html );
    }

    /**
     * WHY: Arrow buttons start hidden so there's no flash of unstyled UI on
     * load before JS measures the track. Canyon's original file was missing
     * this attribute entirely.
     */
    #[Test]
    public function arrowButtonsHaveHiddenAttribute(): void
    {
        $html = PromotionsCarouselHelper::render(
            [ $this->makeCard(), $this->makeCard( [ 'title' => 'Card 2' ] ) ],
            'Bay',
            'cbv'
        );

        $this->assertStringContainsString( 'aria-label="Previous promotions" hidden>', $html );
        $this->assertStringContainsString( 'aria-label="Next promotions" hidden>', $html );
    }

    /**
     * WHY: Empty cards array must return empty string — no section wrapper output.
     */
    #[Test]
    public function renderWithEmptyCardsReturnsEmptyString(): void
    {
        $this->assertSame( '', PromotionsCarouselHelper::render( [], 'Bay', 'cbv' ) );
    }

    // ── renderCard() — CSS prefix on card elements ────────────────────────────

    /**
     * WHY: Every element inside a card uses the same prefix. A single wrong
     * prefix would break the card's CSS entirely.
     */
    #[Test]
    #[DataProvider( 'prefixLabelProvider' )]
    public function renderCardUsesCorrectPrefixOnAllElements( string $prefix, string $label ): void
    {
        $html = PromotionsCarouselHelper::renderCard( $this->makeCard(), $prefix );

        $this->assertStringContainsString( '"' . $prefix . '-card"',             $html );
        $this->assertStringContainsString( '"' . $prefix . '-card__image-wrap"', $html );
        $this->assertStringContainsString( '"' . $prefix . '-card__title"',      $html );
        $this->assertStringContainsString( '"' . $prefix . '-card__wave"',       $html );
        $this->assertStringContainsString( '"' . $prefix . '-card__button"',     $html );
    }

    // ── renderCard() — content ────────────────────────────────────────────────

    #[Test]
    public function renderCardContainsTitleInH3(): void
    {
        $html = PromotionsCarouselHelper::renderCard( $this->makeCard(), 'cbv' );

        $this->assertStringContainsString(
            '<h3 class="cbv-card__title">Family Fun Pack</h3>',
            $html
        );
    }

    #[Test]
    public function renderCardShowsPriceWhenPresent(): void
    {
        $html = PromotionsCarouselHelper::renderCard( $this->makeCard(), 'cbv' );

        $this->assertStringContainsString( '<div class="cbv-card__price">$39.99</div>', $html );
    }

    #[Test]
    public function renderCardOmitsPriceDivWhenEmpty(): void
    {
        $html = PromotionsCarouselHelper::renderCard( $this->makeCard( [ 'price' => '' ] ), 'cbv' );

        $this->assertStringNotContainsString( 'card__price', $html );
    }

    #[Test]
    public function renderCardOmitsImageWrapWhenUrlEmpty(): void
    {
        $html = PromotionsCarouselHelper::renderCard( $this->makeCard( [ 'img_url' => '' ] ), 'cbv' );

        $this->assertStringNotContainsString( 'card__image-wrap', $html );
        $this->assertStringNotContainsString( '<img', $html );
    }

    #[Test]
    public function renderCardContainsCorrectPermalinkHref(): void
    {
        $html = PromotionsCarouselHelper::renderCard( $this->makeCard(), 'cbv' );

        $this->assertStringContainsString(
            'href="https://example.com/promotions/family-fun-pack/"',
            $html
        );
    }

    // ── renderCard() — date icon switching ───────────────────────────────────

    #[Test]
    public function renderCardUsesCalendarIconWhenHasDate(): void
    {
        $html = PromotionsCarouselHelper::renderCard(
            $this->makeCard( [ 'has_date' => true ] ), 'cbv'
        );

        $this->assertStringContainsString( '<rect x="3" y="4"', $html );
        $this->assertStringNotContainsString( '<circle cx="12"', $html );
    }

    #[Test]
    public function renderCardUsesClockIconWhenNoDate(): void
    {
        $html = PromotionsCarouselHelper::renderCard(
            $this->makeCard( [ 'has_date' => false, 'date_label' => 'Limited Time' ] ), 'cbv'
        );

        $this->assertStringContainsString( '<circle cx="12"', $html );
        $this->assertStringNotContainsString( '<rect x="3" y="4"', $html );
    }

    #[Test]
    public function renderCardDtRowAriaLabelMatchesHasDate(): void
    {
        $withDate    = PromotionsCarouselHelper::renderCard( $this->makeCard( [ 'has_date' => true ] ), 'cbv' );
        $withoutDate = PromotionsCarouselHelper::renderCard( $this->makeCard( [ 'has_date' => false ] ), 'cbv' );

        $this->assertStringContainsString( 'aria-label="End date"',  $withDate );
        $this->assertStringContainsString( 'aria-label="Duration"', $withoutDate );
    }

    #[Test]
    public function renderCardDateLabelAppearsInSpan(): void
    {
        $html = PromotionsCarouselHelper::renderCard(
            $this->makeCard( [ 'date_label' => 'Ends – December 31st', 'has_date' => true ] ),
            'cbv'
        );

        // esc_html passes the UTF-8 en-dash through as a literal character.
        $this->assertStringContainsString(
            '<span class="cbv-card__date">Ends – December 31st</span>',
            $html
        );
    }

    // ── XSS / security ───────────────────────────────────────────────────────

    #[Test]
    public function renderCardEscapesTitleToPreventXss(): void
    {
        $html = PromotionsCarouselHelper::renderCard(
            $this->makeCard( [ 'title' => '<script>alert("xss")</script>' ] ), 'cbv'
        );

        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringContainsString( '&lt;script&gt;', $html );
    }

    #[Test]
    public function renderCardEscapesTitleInAriaLabel(): void
    {
        $html = PromotionsCarouselHelper::renderCard(
            $this->makeCard( [ 'title' => 'Save "Big" Today' ] ), 'cbv'
        );

        $this->assertStringContainsString(
            'aria-label="See details for Save &quot;Big&quot; Today"',
            $html
        );
    }

    #[Test]
    public function renderCardStripsJavascriptProtocolFromImgUrl(): void
    {
        $html = PromotionsCarouselHelper::renderCard(
            $this->makeCard( [ 'img_url' => 'javascript:alert(1)' ] ), 'cbv'
        );

        $this->assertStringNotContainsString( 'javascript:alert', $html );
    }

    #[Test]
    public function renderCardEscapesImgAltAttribute(): void
    {
        $html = PromotionsCarouselHelper::renderCard(
            $this->makeCard( [ 'img_alt' => '" onload="alert(1)' ] ), 'cbv'
        );

        $this->assertStringContainsString( 'alt="&quot; onload=&quot;alert(1)"', $html );
        $this->assertStringNotContainsString( 'alt="" onload="alert(1)"', $html );
    }

    /**
     * WHY: The location label is inserted into an aria-label attribute.
     * A label containing quotes must be encoded so it can't break out of the attribute.
     */
    #[Test]
    public function renderEscapesLocationLabelInAriaAttribute(): void
    {
        $html = PromotionsCarouselHelper::render(
            [ $this->makeCard() ],
            'Bay "North"',
            'cbv'
        );

        $this->assertStringContainsString(
            'aria-label="Bay &quot;North&quot; Promotions"',
            $html
        );
    }

    // ── Full pipeline ─────────────────────────────────────────────────────────

    /**
     * WHY: Smoke-tests the full chain for both prefix families in one pass.
     * Catches any regression where the prefix parameter doesn't flow through
     * all the way from render() down to individual card elements.
     */
    #[Test]
    #[DataProvider( 'prefixLabelProvider' )]
    public function fullPipelineProducesCorrectHtmlForBothPrefixes( string $prefix, string $label ): void
    {
        $cards = [
            PromotionsCarouselHelper::buildCard(
                [
                    'promotion_images'   => [ '4x3_image' => 'https://example.com/a.jpg', 'alt_text' => 'Promo A' ],
                    'promotion_end_date' => '20261201',
                    'priority'           => 2,
                    'promotion_name'     => 'Promo A',
                    'promotion_price'    => '$19.99',
                ],
                'WP Title A',
                'https://example.com/promo-a/'
            ),
            PromotionsCarouselHelper::buildCard(
                [
                    'promotion_images'   => [ '4x3_image' => 'https://example.com/b.jpg', 'alt_text' => 'Promo B' ],
                    'promotion_end_date' => false,
                    'priority'           => 1,
                    'promotion_name'     => 'Promo B',
                    'promotion_price'    => false,
                ],
                'WP Title B',
                'https://example.com/promo-b/'
            ),
        ];

        $html = PromotionsCarouselHelper::render(
            PromotionsCarouselHelper::sortCards( $cards ),
            $label,
            $prefix
        );

        // Promo B (priority 1) must appear before Promo A (priority 2).
        $this->assertLessThan( strpos( $html, 'Promo A' ), strpos( $html, 'Promo B' ) );

        // Section uses the right prefix and label.
        $this->assertStringContainsString( $prefix . '-carousel--promotions', $html );
        $this->assertStringContainsString( 'aria-label="' . $label . ' Promotions"', $html );

        // Cards use the right prefix.
        $this->assertStringContainsString( '"' . $prefix . '-card"', $html );

        // Arrows present (2 cards).
        $this->assertStringContainsString( $prefix . '-carousel__arrow--prev', $html );
    }
}
