<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class SweepData implements \JsonSerializable
{
    public function __construct(
        public CardCollection $remainingCards,
        public ?int $lastCapturer,
    ) {}

    /** @return array{remainingCards: list<array{suit: string, value: int}>, lastCapturer: int|null} */
    public function jsonSerialize(): array
    {
        return [
            'remainingCards' => $this->remainingCards->jsonSerialize(),
            'lastCapturer' => $this->lastCapturer,
        ];
    }
}
