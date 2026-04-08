<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Enum\GameType;

final readonly class GameLookupOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $state,
        public GameType $gameType = GameType::Scopa,
    ) {}
}
