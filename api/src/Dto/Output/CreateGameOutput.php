<?php

declare(strict_types=1);

namespace App\Dto\Output;

final readonly class CreateGameOutput
{
    public function __construct(
        public string $gameId,
        public string $playerToken,
        public string $state,
        public ?GameStateOutput $gameState = null,
    ) {}
}
