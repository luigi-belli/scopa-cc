<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;

final class ScoringService
{
    private const PRIMIERA_VALUES = [
        7 => 21, 6 => 18, 1 => 16, 5 => 15,
        4 => 14, 3 => 13, 2 => 12,
        8 => 10, 9 => 10, 10 => 10,
    ];

    public function scoreRound(Game $game): array
    {
        $p1Captured = $game->getPlayer1Captured();
        $p2Captured = $game->getPlayer2Captured();

        $scores = [
            ['carte' => 0, 'denari' => 0, 'setteBello' => 0, 'primiera' => 0, 'scope' => 0],
            ['carte' => 0, 'denari' => 0, 'setteBello' => 0, 'primiera' => 0, 'scope' => 0],
        ];

        // Carte (most cards)
        $c1 = count($p1Captured);
        $c2 = count($p2Captured);
        if ($c1 > $c2) {
            $scores[0]['carte'] = 1;
        } elseif ($c2 > $c1) {
            $scores[1]['carte'] = 1;
        }

        // Denari (most Denari suit)
        $d1 = $this->countSuit($p1Captured, 'Denari');
        $d2 = $this->countSuit($p2Captured, 'Denari');
        if ($d1 > $d2) {
            $scores[0]['denari'] = 1;
        } elseif ($d2 > $d1) {
            $scores[1]['denari'] = 1;
        }

        // Sette Bello (7 of Denari)
        if ($this->hasCard($p1Captured, 'Denari', 7)) {
            $scores[0]['setteBello'] = 1;
        } elseif ($this->hasCard($p2Captured, 'Denari', 7)) {
            $scores[1]['setteBello'] = 1;
        }

        // Primiera
        $prim1 = $this->calculatePrimiera($p1Captured);
        $prim2 = $this->calculatePrimiera($p2Captured);
        if ($prim1 !== null && $prim2 !== null) {
            if ($prim1 > $prim2) {
                $scores[0]['primiera'] = 1;
            } elseif ($prim2 > $prim1) {
                $scores[1]['primiera'] = 1;
            }
        } elseif ($prim1 !== null) {
            $scores[0]['primiera'] = 1;
        } elseif ($prim2 !== null) {
            $scores[1]['primiera'] = 1;
        }

        // Scope
        $scores[0]['scope'] = $game->getPlayer1Scope();
        $scores[1]['scope'] = $game->getPlayer2Scope();

        return $scores;
    }

    public function totalRoundScore(array $scoreRow): int
    {
        return $scoreRow['carte'] + $scoreRow['denari'] + $scoreRow['setteBello']
            + $scoreRow['primiera'] + $scoreRow['scope'];
    }

    public function getPrimieraValue(int $cardValue): int
    {
        return self::PRIMIERA_VALUES[$cardValue] ?? 0;
    }

    private function countSuit(array $cards, string $suit): int
    {
        $count = 0;
        foreach ($cards as $card) {
            if ($card['suit'] === $suit) {
                $count++;
            }
        }
        return $count;
    }

    private function hasCard(array $cards, string $suit, int $value): bool
    {
        foreach ($cards as $card) {
            if ($card['suit'] === $suit && $card['value'] === $value) {
                return true;
            }
        }
        return false;
    }

    private function calculatePrimiera(array $cards): ?int
    {
        $bestPerSuit = [];
        foreach ($cards as $card) {
            $suit = $card['suit'];
            $pVal = self::PRIMIERA_VALUES[$card['value']];
            if (!isset($bestPerSuit[$suit]) || $pVal > $bestPerSuit[$suit]) {
                $bestPerSuit[$suit] = $pVal;
            }
        }

        if (count($bestPerSuit) < 4) {
            return null;
        }

        return array_sum($bestPerSuit);
    }
}
