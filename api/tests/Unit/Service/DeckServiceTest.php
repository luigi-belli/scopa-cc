<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\Suit;
use App\Service\DeckService;
use App\ValueObject\CardCollection;
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

        foreach (Suit::cases() as $suit) {
            $this->assertEquals(10, $deck->countBySuit($suit));
        }

        // Verify no duplicates
        $keys = array_map(fn($c) => $c->suit->value . '-' . $c->value, $deck->toArray());
        $this->assertCount(40, array_unique($keys));

        // Verify values 1-10
        foreach (Suit::cases() as $suit) {
            $values = [];
            foreach ($deck as $card) {
                if ($card->suit === $suit) {
                    $values[] = $card->value;
                }
            }
            sort($values);
            $this->assertEquals(range(1, 10), $values);
        }
    }

    public function testShuffle(): void
    {
        $deck1 = $this->service->createDeck();
        $deck2 = $this->service->shuffle($deck1);

        $this->assertCount(40, $deck2);

        $keys1 = array_map(fn($c) => $c->suit->value . '-' . $c->value, $deck1->toArray());
        $keys2 = array_map(fn($c) => $c->suit->value . '-' . $c->value, $deck2->toArray());
        sort($keys1);
        sort($keys2);
        $this->assertEquals($keys1, $keys2);

        // Very unlikely to be in same order
        $this->assertNotEquals($deck1->jsonSerialize(), $deck2->jsonSerialize());
    }
}
