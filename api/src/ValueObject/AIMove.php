<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class AIMove
{
    public function __construct(
        public int $cardIndex,
        public ?int $optionIndex = null,
    ) {}
}
