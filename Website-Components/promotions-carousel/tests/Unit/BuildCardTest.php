<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PromotionsCarouselHelper;

/**
 * Tests for PromotionsCarouselHelper::buildCard()
 *
 * WHY this test class exists:
 *   buildCard() is the translation layer between raw ACF data (which is full of
 *   false/null/missing-key landmines) and the clean typed array the render
 *   methods expect. These tests verify every normalization rule and ensure the
 *   ACF false-vs-null bug cannot resurface.
 */
final class BuildCardTest extends TestCase
{
    // ── Fixtures ──────────────────────────────────────────────────────────────

    /**
     * A complete, valid set of ACF field values — the happy path.
     */
    private function fullFields(): array
    {
        return [
            'promotion_images'   => [
                '4x3_image' => 'https://example.com/img/summer-pass.jpg',
                'alt_text'  => 'Summer Pass promotion banner',
            ],
            'promotion_end_date' => '20261231',
            'priority'           => 2,
            'promotion_name'     => 'Summer Fun Pack',
            'promotion_price'    => '$49.99',
        ];
    }

    /**
     * A set of ACF field values where every field is the false ACF empty value.
     * This is the state you get when a post exists but the editor never filled
     * in any ACF fields.
     */
    private function emptyAcfFields(): array
    {
        return [
            'promotion_images'   => false,
            'promotion_end_date' => false,
            'priority'           => false,
            'promotion_name'     => false,
            'promotion_price'    => false,
        ];
    }

    // ── Happy-path ────────────────────────────────────────────────────────────

    /**
     * WHY: Baseline test. Confirms every key in the output array is present and
     * has the correct type and value when all ACF fields are fully populated.
     */
    #[Test]
    public function fullFieldsProducesCorrectCardArray(): void
    {
        $card = PromotionsCarouselHelper::buildCard(
            $this->fullFields(),
            'Fallback Title',
            'https://example.com/promotions/summer-fun-pack/'
        );

        $this->assertSame('Summer Fun Pack', $card['title']);
        $this->assertSame('$49.99', $card['price']);
        $this->assertSame('https://example.com/img/summer-pass.jpg', $card['img_url']);
        $this->assertSame('Summer Pass promotion banner', $card['img_alt']);
        $this->assertSame('Ends – December 31st', $card['date_label']);
        $this->assertTrue($card['has_date']);
        $this->assertSame('https://example.com/promotions/summer-fun-pack/', $card['permalink']);
        $this->assertSame(2, $card['priority']);
    }

    /**
     * WHY: The return shape contract. Render methods index into these keys
     * directly — any missing key would cause an undefined index notice in PHP 8.
     */
    #[Test]
    public function returnArrayAlwaysContainsAllRequiredKeys(): void
    {
        $card = PromotionsCarouselHelper::buildCard(
            $this->emptyAcfFields(),
            'Fallback',
            'https://example.com/'
        );

        foreach (['title', 'price', 'img_url', 'img_alt', 'date_label', 'has_date', 'permalink', 'priority'] as $key) {
            $this->assertArrayHasKey($key, $card, "Missing key: $key");
        }
    }

    // ── ACF false handling (critical) ─────────────────────────────────────────

    /**
     * WHY: THE most important test in the suite. ACF returns false (not null)
     * for empty fields. The original code used ?? which only catches null, so
     * $images would be false and $images['4x3_image'] would throw a PHP 8 Warning.
     * This test guards against that regression.
     */
    #[Test]
    public function acfFalseForImagesDoesNotCauseWarning(): void
    {
        $fields = $this->emptyAcfFields();
        $fields['promotion_images'] = false;

        // If this triggers "Trying to access array offset on false", the test fails
        // via PHPUnit's failOnWarning setting in phpunit.xml.
        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame('', $card['img_url']);
    }

    /**
     * WHY: Same scenario but null instead of false. Null can appear if code
     * explicitly sets a field to null or if ACF behaviour changes between versions.
     */
    #[Test]
    public function nullForImagesProducesEmptyImgUrl(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_images'] = null;

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame('', $card['img_url']);
    }

    /**
     * WHY: promotion_images being a non-array scalar (e.g. if ACF is misconfigured
     * to return URL string directly instead of group) must not cause an array
     * access warning.
     */
    #[Test]
    public function scalarForImagesProducesEmptyImgUrl(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_images'] = 'https://example.com/img.jpg'; // scalar, not group array

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame('', $card['img_url']);
    }

    // ── Title fallback ────────────────────────────────────────────────────────

    /**
     * WHY: When promotion_name is false (ACF empty), the card title must fall
     * back to the WordPress post title passed as $fallbackTitle.
     */
    #[Test]
    public function acfFalseForNameFallsBackToWpTitle(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_name'] = false;

        $card = PromotionsCarouselHelper::buildCard($fields, 'WP Post Title', 'https://example.com/');

        $this->assertSame('WP Post Title', $card['title']);
    }

    /**
     * WHY: An empty string for promotion_name should also trigger the fallback.
     * A field with an empty string is functionally identical to "not set".
     */
    #[Test]
    public function emptyStringForNameFallsBackToWpTitle(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_name'] = '';

        $card = PromotionsCarouselHelper::buildCard($fields, 'WP Post Title', 'https://example.com/');

        $this->assertSame('WP Post Title', $card['title']);
    }

    /**
     * WHY: A whitespace-only promotion_name is functionally empty but is NOT
     * an empty string. Document the current behaviour: it's used as-is.
     * (If you want to trim it, add that test expectation here.)
     */
    #[Test]
    public function whitespacePromotionNameIsUsedAsIs(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_name'] = '   ';

        $card = PromotionsCarouselHelper::buildCard($fields, 'WP Post Title', 'https://example.com/');

        // '   ' is a non-empty string, so it's used directly (not fallen back to WP title).
        $this->assertSame('   ', $card['title']);
    }

    // ── Priority handling ─────────────────────────────────────────────────────

    /**
     * WHY: Priority 0 is a valid "show first" value. The original code used
     * ?? 999 which kept 0 correctly (since ?? only catches null). But if the
     * fix for the false-issue was naively ?: 999, then 0 ?: 999 === 999 (BUG).
     * This test locks in that 0 must remain 0.
     */
    #[Test]
    public function priorityZeroIsPreservedNotOverriddenTo999(): void
    {
        $fields = $this->fullFields();
        $fields['priority'] = 0;

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame(0, $card['priority']);
    }

    /**
     * WHY: When priority is false (ACF unset), it must default to 999 so the
     * card sorts to the end of the list, matching the original design intent.
     */
    #[Test]
    public function acfFalseForPriorityDefaultsTo999(): void
    {
        $fields = $this->fullFields();
        $fields['priority'] = false;

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame(999, $card['priority']);
    }

    /**
     * WHY: ACF number fields can return strings when the field type is "text"
     * and the value happens to be numeric. (int) cast must handle this.
     */
    #[Test]
    public function stringNumericPriorityIsCastToInt(): void
    {
        $fields = $this->fullFields();
        $fields['priority'] = '3';

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame(3, $card['priority']);
    }

    /**
     * WHY: An empty string for priority should fall back to 999 (not 0).
     */
    #[Test]
    public function emptyStringPriorityDefaultsTo999(): void
    {
        $fields = $this->fullFields();
        $fields['priority'] = '';

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame(999, $card['priority']);
    }

    // ── Image alt text fallback ───────────────────────────────────────────────

    /**
     * WHY: If alt_text is missing from the images group, the card must use the
     * post title as alt text. Broken images without alt text are both an
     * accessibility failure and an SEO issue.
     */
    #[Test]
    public function missingAltTextFallsBackToFallbackTitle(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_images'] = ['4x3_image' => 'https://example.com/img.jpg']; // no alt_text key

        $card = PromotionsCarouselHelper::buildCard($fields, 'Post Title for Alt', 'https://example.com/');

        $this->assertSame('Post Title for Alt', $card['img_alt']);
    }

    /**
     * WHY: alt_text being null (ACF group sub-field returning null) should also
     * fall back to the post title.
     */
    #[Test]
    public function nullAltTextFallsBackToFallbackTitle(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_images']['alt_text'] = null;

        $card = PromotionsCarouselHelper::buildCard($fields, 'Post Title for Alt', 'https://example.com/');

        $this->assertSame('Post Title for Alt', $card['img_alt']);
    }

    // ── Price handling ────────────────────────────────────────────────────────

    /**
     * WHY: Price is optional. When ACF returns false, it must produce an empty
     * string (not "false") so the render method's $card['price'] !== '' guard works.
     */
    #[Test]
    public function acfFalseForPriceProducesEmptyString(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_price'] = false;

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame('', $card['price']);
    }

    /**
     * WHY: A numeric price (e.g. 29.99 from a number ACF field) must be cast
     * to a string so downstream render logic always handles a string.
     */
    #[Test]
    public function numericPriceIsCastToString(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_price'] = 29.99;

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame('29.99', $card['price']);
    }

    // ── Date handling via formatEndDate integration ───────────────────────────

    /**
     * WHY: When end date is false (ACF unset), the card must show "Limited Time"
     * and has_date must be false so the clock icon (not calendar icon) is rendered.
     */
    #[Test]
    public function acfFalseForEndDateProducesLimitedTimeLabel(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_end_date'] = false;

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame('Limited Time', $card['date_label']);
        $this->assertFalse($card['has_date']);
    }

    /**
     * WHY: A valid end date must flow through formatEndDate correctly and
     * set has_date to true so the calendar icon is shown.
     */
    #[Test]
    public function validEndDateProducesCorrectLabelAndSetsHasDateTrue(): void
    {
        $fields = $this->fullFields();
        $fields['promotion_end_date'] = '20260704';

        $card = PromotionsCarouselHelper::buildCard($fields, 'Title', 'https://example.com/');

        $this->assertSame('Ends – July 4th', $card['date_label']);
        $this->assertTrue($card['has_date']);
    }

    // ── Permalink passthrough ─────────────────────────────────────────────────

    /**
     * WHY: The permalink comes from WordPress get_permalink() and is passed
     * straight through — buildCard must not mutate it.
     */
    #[Test]
    public function permalinkIsStoredVerbatim(): void
    {
        $url = 'https://crystalbeachvacations.com/promotions/summer-fun-pack/';

        $card = PromotionsCarouselHelper::buildCard(
            $this->fullFields(),
            'Title',
            $url
        );

        $this->assertSame($url, $card['permalink']);
    }
}
