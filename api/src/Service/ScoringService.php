<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Enum\Suit;
use App\ValueObject\CardCollection;
use App\ValueObject\RoundScores;
use App\ValueObject\ScoreRow;

final class ScoringService
{
    private const PRIMIERA_VALUES = [
        7 => 21, 6 => 18, 1 => 16, 5 => 15,
        4 => 14, 3 => 13, 2 => 12,
        8 => 10, 9 => 10, 10 => 10,
    ];

    public function scoreRound(Game $game): RoundScores
    {
        $p1Captured = $game->getPlayer1Captured();
        $p2Captured = $game->getPlayer2Captured();

        $c1 = count($p1Captured);
        $c2 = count($p2Captured);
        $d1 = $p1Captured->countBySuit(Suit::Denari);
        $d2 = $p2Captured->countBySuit(Suit::Denari);
        $prim1 = $this->calculatePrimiera($p1Captured);
        $prim2 = $this->calculatePrimiera($p2Captured);

        $s = [
            ['carte' => 0, 'denari' => 0, 'setteBello' => 0, 'primiera' => 0, 'scope' => 0,
             'carteCount' => $c1, 'denariCount' => $d1, 'primieraValue' => $prim1, 'hasSetteBello' => false,
             'carteCards' => $p1Captured, 'denariCards' => $p1Captured->filterBySuit(Suit::Denari),
             'primieraCards' => $this->getBestPrimieraCards($p1Captured)],
            ['carte' => 0, 'denari' => 0, 'setteBello' => 0, 'primiera' => 0, 'scope' => 0,
             'carteCount' => $c2, 'denariCount' => $d2, 'primieraValue' => $prim2, 'hasSetteBello' => false,
             'carteCards' => $p2Captured, 'denariCards' => $p2Captured->filterBySuit(Suit::Denari),
             'primieraCards' => $this->getBestPrimieraCards($p2Captured)],
        ];

        if ($c1 > $c2) {
            $s[0]['carte'] = 1;
        } elseif ($c2 > $c1) {
            $s[1]['carte'] = 1;
        }

        if ($d1 > $d2) {
            $s[0]['denari'] = 1;
        } elseif ($d2 > $d1) {
            $s[1]['denari'] = 1;
        }

        if ($p1Captured->hasCard(Suit::Denari, 7)) {
            $s[0]['setteBello'] = 1;
            $s[0]['hasSetteBello'] = true;
        } elseif ($p2Captured->hasCard(Suit::Denari, 7)) {
            $s[1]['setteBello'] = 1;
            $s[1]['hasSetteBello'] = true;
        }

        if ($prim1 !== null && $prim2 !== null) {
            if ($prim1 > $prim2) {
                $s[0]['primiera'] = 1;
            } elseif ($prim2 > $prim1) {
                $s[1]['primiera'] = 1;
            }
        } elseif ($prim1 !== null) {
            $s[0]['primiera'] = 1;
        } elseif ($prim2 !== null) {
            $s[1]['primiera'] = 1;
        }

        $s[0]['scope'] = $game->getPlayer1Scope();
        $s[1]['scope'] = $game->getPlayer2Scope();

        return new RoundScores(
            new ScoreRow(...$s[0]),
            new ScoreRow(...$s[1]),
        );
    }

    public function getPrimieraValue(int $cardValue): int
    {
        return self::PRIMIERA_VALUES[$cardValue] ?? 0;
    }

    private function getBestPrimieraCards(CardCollection $cards): CardCollection
    {
        /** @var array<string, \App\ValueObject\Card> $bestPerSuit */
        $bestPerSuit = [];
        foreach ($cards as $card) {
            $suit = $card->suit->value;
            $pVal = self::PRIMIERA_VALUES[$card->value];
            if (!isset($bestPerSuit[$suit]) || $pVal > self::PRIMIERA_VALUES[$bestPerSuit[$suit]->value]) {
                $bestPerSuit[$suit] = $card;
            }
        }

        return new CardCollection(array_values($bestPerSuit));
    }

    private function calculatePrimiera(CardCollection $cards): ?int
    {
        $best = $this->getBestPrimieraCards($cards);

        if (count($best) < 4) {
            return null;
        }

        $sum = 0;
        foreach ($best as $card) {
            $sum += self::PRIMIERA_VALUES[$card->value];
        }

        return $sum;
    }
}
