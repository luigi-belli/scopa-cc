<?php

declare(strict_types=1);

namespace App\Message;

final readonly class HandleAITurnMessage
{
    public function __construct(
        public string $gameId,
    ) {}
}
