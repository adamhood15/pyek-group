<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PromotionsCarouselHelper;

/**
 * Tests for PromotionsCarouselHelper::sortCards() and shouldShowArrows()
 *
 * WHY this test class exists:
 *   sortCards() controls the display order of promotions — a high-priority
 *   promo must appear before a low-priority one, and cards without a priority
 *   field (defaulted to 999) must sort to the end. A regression here would
 *   silently show the wrong promotion in the most-visible carousel slot.
 *
 *   shouldShowArrows() is a small but critical UI gate — showing arrows for a
 *   single card would render a broken carousel with non-functional buttons.
 */
final class SortCardsTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeCard(int $priority, string $title = ''): array
    {
        return [
            'title'      => $title ?: "Priority $priority card",
            'price'      => '',
            'img_url'    => '',
            'img_alt'    => '',
            'date_label' => 'Limited Time',
            'has_date'   => false,
            'permalink'  => 'https://example.com/',
            'priority'   => $priority,
        ];
    }

    // ── sortCards ─────────────────────────────────────────────────────────────

    /**
     * WHY: The baseline case — cards out of order must be sorted ascending by
     * priority so the "most important" promotion (lowest number) comes first.
     */
    #[Test]
    public function cardsAreSortedByPriorityAscending(): void
    {
        $cards = [
            $this->makeCard(3, 'Third'),
            $this->makeCard(1, 'First'),
            $this->makeCard(2, 'Second'),
        ];

        $sorted = PromotionsCarouselHelper::sortCards($cards);

        $this->assertSame('First',  $sorted[0]['title']);
        $this->assertSame('Second', $sorted[1]['title']);
        $this->assertSame('Third',  $sorted[2]['title']);
    }

    /**
     * WHY: An already-sorted array must remain in the same order. Confirms
     * the spaceship operator (<=> ) works correctly for equal-or-sorted input.
     */
    #[Test]
    public function alreadySortedArrayRemainsUnchanged(): void
    {
        $cards = [
            $this->makeCard(1, 'First'),
            $this->makeCard(2, 'Second'),
            $this->makeCard(3, 'Third'),
        ];

        $sorted = PromotionsCarouselHelper::sortCards($cards);

        $this->assertSame('First',  $sorted[0]['title']);
        $this->assertSame('Second', $sorted[1]['title']);
        $this->assertSame('Third',  $sorted[2]['title']);
    }

    /**
     * WHY: Priority 0 is valid and must sort before priority 1.
     * This test guards against the ?: 999 regression where 0 would become 999.
     */
    #[Test]
    public function priorityZeroSortsBeforePriorityOne(): void
    {
        $cards = [
            $this->makeCard(1, 'Second'),
            $this->makeCard(0, 'First'),
        ];

        $sorted = PromotionsCarouselHelper::sortCards($cards);

        $this->assertSame('First',  $sorted[0]['title']);
        $this->assertSame('Second', $sorted[1]['title']);
    }

    /**
     * WHY: Cards with no priority field default to 999. They must sort to the
     * end behind any card with an explicit priority, regardless of insertion order.
     */
    #[Test]
    public function defaultPriority999SortsToEnd(): void
    {
        $cards = [
            $this->makeCard(999, 'No Priority (defaulted)'),
            $this->makeCard(5,   'Explicit Priority 5'),
            $this->makeCard(1,   'Explicit Priority 1'),
        ];

        $sorted = PromotionsCarouselHelper::sortCards($cards);

        $this->assertSame('Explicit Priority 1',      $sorted[0]['title']);
        $this->assertSame('Explicit Priority 5',      $sorted[1]['title']);
        $this->assertSame('No Priority (defaulted)',  $sorted[2]['title']);
    }

    /**
     * WHY: All cards sharing the same priority is valid (e.g. all unset → all 999).
     * The sort must not crash and must return all cards.
     */
    #[Test]
    public function cardsWithIdenticalPrioritiesAreAllReturned(): void
    {
        $cards = [
            $this->makeCard(999, 'Card A'),
            $this->makeCard(999, 'Card B'),
            $this->makeCard(999, 'Card C'),
        ];

        $sorted = PromotionsCarouselHelper::sortCards($cards);

        $this->assertCount(3, $sorted);
    }

    /**
     * WHY: A single-card array must return exactly one card and not crash.
     * usort() on a one-element array is a valid edge case.
     */
    #[Test]
    public function singleCardArrayReturnsSingleCard(): void
    {
        $cards = [$this->makeCard(5, 'Only Card')];

        $sorted = PromotionsCarouselHelper::sortCards($cards);

        $this->assertCount(1, $sorted);
        $this->assertSame('Only Card', $sorted[0]['title']);
    }

    /**
     * WHY: An empty array must return an empty array, not throw an error.
     * The caller already guards against this, but defensive testing is free.
     */
    #[Test]
    public function emptyArrayReturnsEmptyArray(): void
    {
        $sorted = PromotionsCarouselHelper::sortCards([]);

        $this->assertSame([], $sorted);
    }

    /**
     * WHY: sortCards must not mutate the original array — it should return a
     * new sorted copy. If it mutated the original, callers that hold a reference
     * would see unexpected re-ordering.
     */
    #[Test]
    public function originalArrayIsNotMutated(): void
    {
        $cards = [
            $this->makeCard(3, 'Third'),
            $this->makeCard(1, 'First'),
        ];
        $original = $cards; // shallow copy before sort

        PromotionsCarouselHelper::sortCards($cards);

        $this->assertSame($original[0]['title'], $cards[0]['title'], 'Original array was mutated');
        $this->assertSame($original[1]['title'], $cards[1]['title'], 'Original array was mutated');
    }

    /**
     * WHY: Large gaps between priority values (1 vs 100 vs 999) must still
     * sort correctly. The spaceship operator handles this but worth confirming.
     */
    #[Test]
    public function largePriorityGapsAreHandledCorrectly(): void
    {
        $cards = [
            $this->makeCard(999, 'Low'),
            $this->makeCard(100, 'Mid'),
            $this->makeCard(1,   'High'),
        ];

        $sorted = PromotionsCarouselHelper::sortCards($cards);

        $this->assertSame('High', $sorted[0]['title']);
        $this->assertSame('Mid',  $sorted[1]['title']);
        $this->assertSame('Low',  $sorted[2]['title']);
    }

    // ── shouldShowArrows ──────────────────────────────────────────────────────

    /**
     * WHY: Arrow buttons serve no purpose (and confuse screen readers) when
     * there's only one card. They must be suppressed.
     */
    #[Test]
    public function singleCardDoesNotShowArrows(): void
    {
        $this->assertFalse(
            PromotionsCarouselHelper::shouldShowArrows([$this->makeCard(1)])
        );
    }

    /**
     * WHY: Exactly two cards is the minimum number that makes arrows useful.
     */
    #[Test]
    public function twoCardsShowArrows(): void
    {
        $this->assertTrue(
            PromotionsCarouselHelper::shouldShowArrows([
                $this->makeCard(1),
                $this->makeCard(2),
            ])
        );
    }

    /**
     * WHY: Many cards must also show arrows — confirm there's no off-by-one
     * error at any count above 2.
     */
    #[Test]
    public function manyCardsShowArrows(): void
    {
        $cards = array_map(fn($i) => $this->makeCard($i), range(1, 10));

        $this->assertTrue(PromotionsCarouselHelper::shouldShowArrows($cards));
    }

    /**
     * WHY: An empty array (which the render() method guards against earlier)
     * must return false — no arrows for zero cards.
     */
    #[Test]
    public function emptyArrayDoesNotShowArrows(): void
    {
        $this->assertFalse(PromotionsCarouselHelper::shouldShowArrows([]));
    }
}
