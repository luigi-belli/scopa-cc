<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\Suit;

final readonly class Card implements \JsonSerializable
{
    public function __construct(
        public Suit $suit,
        public int $value,
    ) {}

    /** @param array{suit: string, value: int} $data */
    public static function fromArray(array $data): self
    {
        return new self(Suit::from($data['suit']), $data['value']);
    }

    /** @return array{suit: string, value: int} */
    public function jsonSerialize(): array
    {
        return ['suit' => $this->suit->value, 'value' => $this->value];
    }

    public function isSetteBello(): bool
    {
        return $this->suit === Suit::Denari && $this->value === 7;
    }

    public function equals(self $other): bool
    {
        return $this->suit === $other->suit && $this->value === $other->value;
    }
}
