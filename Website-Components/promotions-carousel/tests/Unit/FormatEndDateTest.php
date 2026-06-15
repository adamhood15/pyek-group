<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PromotionsCarouselHelper;

/**
 * Tests for PromotionsCarouselHelper::formatEndDate()
 *
 * WHY this test class exists:
 *   formatEndDate() is the only pure PHP function in the component and the one
 *   most likely to misfire silently — wrong format, overflow date, ACF returning
 *   false — all produce no visible PHP error but render incorrect UI to users.
 *   These tests lock in the exact output contract so any regression is caught
 *   before it reaches production.
 */
final class FormatEndDateTest extends TestCase
{
    // ── Happy-path label formatting ───────────────────────────────────────────

    /**
     * WHY: Verifies the baseline: a valid date produces the correct label string
     * and the has_date flag is set. The en-dash character (–, U+2013) must be
     * the exact character used in the original source.
     */
    #[Test]
    public function validDateReturnsFormattedLabel(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('20261231');

        $this->assertSame('Ends – December 31st', $result['label']);
        $this->assertTrue($result['has_date']);
    }

    /**
     * WHY: Confirms the return shape is always exactly two keys with the correct
     * types, so callers can safely destructure without isset() guards.
     */
    #[Test]
    public function returnArrayAlwaysHasLabelAndHasDateKeys(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('20261201');

        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('has_date', $result);
        $this->assertIsString($result['label']);
        $this->assertIsBool($result['has_date']);
    }

    // ── Ordinal suffix coverage ───────────────────────────────────────────────

    /**
     * WHY: PHP's jS format produces 1st/2nd/3rd/4th and the teens (11th–13th)
     * are a known ordinal trap ("11st" would be wrong). These tests lock in
     * the exact ordinal strings for edge-case days.
     *
     * @param string $date     Input in Ymd format.
     * @param string $expected Expected 'label' value.
     */
    #[Test]
    #[DataProvider('ordinalProvider')]
    public function ordinalSuffixesAreCorrect(string $date, string $expected): void
    {
        $result = PromotionsCarouselHelper::formatEndDate($date);

        $this->assertSame($expected, $result['label'], "Failed for input: $date");
    }

    public static function ordinalProvider(): array
    {
        return [
            '1st'  => ['20261101', 'Ends – November 1st'],
            '2nd'  => ['20261102', 'Ends – November 2nd'],
            '3rd'  => ['20261103', 'Ends – November 3rd'],
            '4th'  => ['20261104', 'Ends – November 4th'],
            '11th' => ['20261111', 'Ends – November 11th'],  // teens use "th"
            '12th' => ['20261112', 'Ends – November 12th'],
            '13th' => ['20261113', 'Ends – November 13th'],
            '21st' => ['20261121', 'Ends – November 21st'],  // 21st, not 21th
            '22nd' => ['20261122', 'Ends – November 22nd'],
            '23rd' => ['20261123', 'Ends – November 23rd'],
            '30th' => ['20261130', 'Ends – November 30th'],
            '31st' => ['20261231', 'Ends – December 31st'],
        ];
    }

    // ── Leap year ─────────────────────────────────────────────────────────────

    /**
     * WHY: Feb 29 is only valid in leap years. 2028 IS a leap year (divisible
     * by 4, not a century). This test confirms the date is accepted and formatted
     * correctly rather than being rejected as invalid.
     */
    #[Test]
    public function leapYearFeb29IsAccepted(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('20280229');

        $this->assertSame('Ends – February 29th', $result['label']);
        $this->assertTrue($result['has_date']);
    }

    /**
     * WHY: 2027 is NOT a leap year. PHP's DateTime rolls Feb 29, 2027 over to
     * March 1, triggering a warning. Without a getLastErrors() check, the original
     * code silently displayed "Ends – March 1st" — an incorrect date that would
     * confuse customers. The refactored code must reject this.
     */
    #[Test]
    public function nonLeapYearFeb29ReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('20270229');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }

    /**
     * WHY: Feb 30 is always invalid. PHP rolls it over to March 1/2 with a
     * warning. Same bug as the non-leap-year case above — must return Limited Time.
     */
    #[Test]
    public function feb30AlwaysReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('20260230');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }

    // ── Empty / whitespace inputs ─────────────────────────────────────────────

    /**
     * WHY: ACF returns false for an unset date field. The calling code casts it
     * to '' before passing here. An empty string must become 'Limited Time'
     * and must NOT cause a PHP error.
     */
    #[Test]
    public function emptyStringReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }

    /**
     * WHY: If a user accidentally enters spaces into the ACF field, the trim()
     * call must normalise it to empty before we attempt DateTime parsing.
     */
    #[Test]
    public function whitespaceOnlyStringReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('   ');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }

    /**
     * WHY: Some ACF date pickers can be configured to return "Y-m-d" format
     * instead of "Ymd". A mis-configured field would silently produce wrong
     * output if we didn't validate the format strictly.
     */
    #[Test]
    public function leadingAndTrailingWhitespaceIsTrimmedBeforeParsing(): void
    {
        // Valid date with surrounding spaces — trim() must handle this.
        $result = PromotionsCarouselHelper::formatEndDate('  20261231  ');

        $this->assertSame('Ends – December 31st', $result['label']);
        $this->assertTrue($result['has_date']);
    }

    // ── Invalid format inputs ─────────────────────────────────────────────────

    /**
     * WHY: "Y-m-d" is a common alternative date format. If ACF is misconfigured
     * to return this format, we must gracefully return Limited Time rather than
     * crashing or displaying garbage.
     */
    #[Test]
    public function isoFormatWithDashesReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('2026-12-31');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }

    /**
     * WHY: "d/m/Y" is another common locale format. Ensures we don't accidentally
     * parse it as a valid Ymd date.
     */
    #[Test]
    public function slashSeparatedFormatReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('31/12/2026');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }

    /**
     * WHY: A completely non-date string (e.g. database corruption, copy-paste
     * error) must be handled safely without exceptions.
     */
    #[Test]
    public function randomStringReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('summer-sale');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }

    /**
     * WHY: A 6-digit string is close to Ymd but missing the day component.
     * DateTime::createFromFormat('Ymd', '202612') should fail or produce a
     * date that triggers a warning. Either way it must not render as valid.
     */
    #[Test]
    public function partialDateStringReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('202612');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }

    /**
     * WHY: All-zeros is structurally Ymd-shaped but semantically invalid
     * (month 0, day 0 don't exist). Must not display as a real date.
     */
    #[Test]
    public function allZeroesStringReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('00000000');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }

    /**
     * WHY: Month 13 doesn't exist. PHP may create an overflow DateTime or
     * return false. Either way the output must be Limited Time.
     */
    #[Test]
    public function invalidMonthReturnsLimitedTime(): void
    {
        $result = PromotionsCarouselHelper::formatEndDate('20261301');

        $this->assertSame('Limited Time', $result['label']);
        $this->assertFalse($result['has_date']);
    }
}
