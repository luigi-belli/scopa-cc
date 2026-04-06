<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\DeckService;
use PHPUnit\Framework\TestCase;

class DeckServiceTest extends TestCase
{
    private DeckService $service;

    protected function setUp(): void
    {
        $this->service = new DeckService();
    }

    public function testCreateDeck(): void
    {
        $deck = $this->service->createDeck();

        $this->assertCount(40, $deck);

        // Verify 4 suits × 10 values
        $suits = ['Denari', 'Coppe', 'Bastoni', 'Spade'];
        foreach ($suits as $suit) {
            $suitCards = array_filter($deck, fn($c) => $c['suit'] === $suit);
            $this->assertCount(10, $suitCards);
        }

        // Verify no duplicates
        $keys = array_map(fn($c) => $c['suit'] . '-' . $c['value'], $deck);
        $this->assertCount(40, array_unique($keys));

        // Verify values 1-10
        foreach ($suits as $suit) {
            $values = array_map(
                fn($c) => $c['value'],
                array_filter($deck, fn($c) => $c['suit'] === $suit)
            );
            sort($values);
            $this->assertEquals(range(1, 10), $values);
        }
    }

    public function testShuffle(): void
    {
        $deck1 = $this->service->createDeck();
        $deck2 = $deck1; // copy
        $this->service->shuffle($deck2);

        // Same cards, just different order (statistically very likely)
        $this->assertCount(40, $deck2);

        $keys1 = array_map(fn($c) => $c['suit'] . '-' . $c['value'], $deck1);
        $keys2 = array_map(fn($c) => $c['suit'] . '-' . $c['value'], $deck2);
        sort($keys1);
        sort($keys2);
        $this->assertEquals($keys1, $keys2);

        // Very unlikely to be in same order
        $this->assertNotEquals($deck1, $deck2);
    }
}
