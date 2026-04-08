<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Enum\GameType;

final class AIServiceFactory
{
    public function __construct(
        private readonly ScopaAIService $scopaAI,
        private readonly BriscolaAIService $briscolaAI,
    ) {}

    public function forGame(Game $game): AIService
    {
        return match ($game->getGameType()) {
            GameType::Scopa => $this->scopaAI,
            GameType::Briscola => $this->briscolaAI,
        };
    }
}
