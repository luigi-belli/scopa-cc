<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Enum\GameType;

final class GameEngineFactory
{
    public function __construct(
        private readonly ScopaEngine $scopaEngine,
        private readonly BriscolaEngine $briscolaEngine,
        private readonly TressetteEngine $tressetteEngine,
    ) {}

    public function forGame(Game $game): GameEngine
    {
        return $this->forType($game->getGameType());
    }

    public function forType(GameType $type): GameEngine
    {
        return match ($type) {
            GameType::Scopa => $this->scopaEngine,
            GameType::Briscola => $this->briscolaEngine,
            GameType::Tressette => $this->tressetteEngine,
        };
    }
}
