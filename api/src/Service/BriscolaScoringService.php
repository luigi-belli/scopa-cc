<?php

declare(strict_types=1);

namespace App\Service;

use App\ValueObject\Card;
use App\ValueObject\CardCollection;

final class BriscolaScoringService
{
    /** Card point values for Briscola (Ace=11, Three=10, King=4, Knight=3, Jack=2, rest=0). */
    private const array CARD_POINTS = [
        1 => 11,  // Asso
        3 => 10,  // Tre
        10 => 4,  // Re
        9 => 3,   // Cavallo
        8 => 2,   // Fante
        2 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0,
    ];

    /** Card strength for trick resolution (higher wins). */
    private const array CARD_STRENGTH = [
        1 => 10,  // Asso (strongest)
        3 => 9,   // Tre
        10 => 8,  // Re
        9 => 7,   // Cavallo
        8 => 6,   // Fante
        7 => 5,
        6 => 4,
        5 => 3,
        4 => 2,
        2 => 1,   // Due (weakest)
    ];

    public function getCardPoints(Card $card): int
    {
        return self::CARD_POINTS[$card->value] ?? 0;
    }

    public function getCardStrength(Card $card): int
    {
        return self::CARD_STRENGTH[$card->value] ?? 0;
    }

    public function countPoints(CardCollection $cards): int
    {
        $total = 0;
        foreach ($cards as $card) {
            $total += self::CARD_POINTS[$card->value] ?? 0;
        }
        return $total;
    }
}
