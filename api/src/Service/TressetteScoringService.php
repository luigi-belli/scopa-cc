<?php

declare(strict_types=1);

namespace App\Service;

use App\ValueObject\Card;
use App\ValueObject\CardCollection;

final class TressetteScoringService
{
    /**
     * Card point values for Tressette (×3 integer system).
     * Asso=3, 2/3/Re/Cavallo/Fante=1, rest=0.
     */
    private const array CARD_POINTS = [
        1 => 3,   // Asso
        2 => 1,   // Due
        3 => 1,   // Tre
        4 => 0,
        5 => 0,
        6 => 0,
        7 => 0,
        8 => 1,   // Fante
        9 => 1,   // Cavallo
        10 => 1,  // Re
    ];

    /**
     * Card strength for trick resolution (higher wins).
     * 3 > 2 > Asso > Re > Cavallo > Fante > 7 > 6 > 5 > 4
     */
    private const array CARD_STRENGTH = [
        1 => 8,   // Asso
        2 => 9,   // Due
        3 => 10,  // Tre (strongest)
        4 => 1,   // Quattro (weakest)
        5 => 2,
        6 => 3,
        7 => 4,
        8 => 5,   // Fante
        9 => 6,   // Cavallo
        10 => 7,  // Re
    ];

    /** Ultima bonus: +3 points for winning the last trick. */
    public const int ULTIMA_BONUS = 3;

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
