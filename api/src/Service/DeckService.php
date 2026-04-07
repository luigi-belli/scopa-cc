<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\Suit;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;

final class DeckService
{
    public function createDeck(): CardCollection
    {
        $cards = [];
        foreach (Suit::cases() as $suit) {
            for ($value = 1; $value <= 10; $value++) {
                $cards[] = new Card($suit, $value);
            }
        }
        return new CardCollection($cards);
    }

    public function shuffle(CardCollection $deck): CardCollection
    {
        return $deck->shuffle();
    }
}
