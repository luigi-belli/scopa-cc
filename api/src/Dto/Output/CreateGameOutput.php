<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Enum\GameType;

final readonly class CreateGameOutput
{
    public function __construct(
        public string $gameId,
        public string $playerToken,
        public string $state,
        public GameType $gameType = GameType::Scopa,
        public ?GameStateOutput $gameState = null,
        public ?string $mercureToken = null,
    ) {}
}
