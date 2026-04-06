<?php

declare(strict_types=1);

namespace App\Dto\Output;

final readonly class JoinGameOutput
{
    public function __construct(
        public string $gameId,
        public string $playerToken,
        public GameStateOutput $gameState,
    ) {}
}
