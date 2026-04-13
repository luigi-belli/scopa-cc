<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Enum\GameType;

final readonly class AIServiceFactory
{
    public function __construct(
        private ScopaAIService $scopaAI,
        private BriscolaAIService $briscolaAI,
        private TressetteAIService $tressetteAI,
    ) {}

    public function forGame(Game $game): AIService
    {
        return match ($game->getGameType()) {
            GameType::Scopa => $this->scopaAI,
            GameType::Briscola => $this->briscolaAI,
            GameType::Tressette => $this->tressetteAI,
        };
    }
}
