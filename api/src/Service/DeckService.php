<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Enum\Suit;

/**
 * @phpstan-import-type Card from Game
 */
final class DeckService
{
    /**
     * @return list<Card>
     */
    public function createDeck(): array
    {
        $deck = [];
        foreach (Suit::cases() as $suit) {
            for ($value = 1; $value <= 10; $value++) {
                $deck[] = ['suit' => $suit->value, 'value' => $value];
            }
        }
        return $deck;
    }

    /**
     * @param list<Card> $deck
     */
    public function shuffle(array &$deck): void
    {
        $n = count($deck);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$deck[$i], $deck[$j]] = [$deck[$j], $deck[$i]];
        }
    }
}
