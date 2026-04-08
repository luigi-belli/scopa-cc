<?php

declare(strict_types=1);

namespace App\ValueObject;

use JsonSerializable;

final readonly class LastTrick implements JsonSerializable
{
    public function __construct(
        public Card $leaderCard,
        public Card $followerCard,
        public int $winnerIndex,
    ) {}

    /** @param array{leaderCard: array{suit: string, value: int}, followerCard: array{suit: string, value: int}, winnerIndex: int} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            leaderCard: Card::fromArray($data['leaderCard']),
            followerCard: Card::fromArray($data['followerCard']),
            winnerIndex: $data['winnerIndex'],
        );
    }

    /** @return array{leaderCard: array{suit: string, value: int}, followerCard: array{suit: string, value: int}, winnerIndex: int} */
    public function jsonSerialize(): array
    {
        return [
            'leaderCard' => $this->leaderCard->jsonSerialize(),
            'followerCard' => $this->followerCard->jsonSerialize(),
            'winnerIndex' => $this->winnerIndex,
        ];
    }
}
