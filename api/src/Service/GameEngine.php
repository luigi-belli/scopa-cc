<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Output\GameStateOutput;
use App\Entity\Game;
use App\ValueObject\TurnResult;

interface GameEngine
{
    public function initializeGame(Game $game): void;

    public function startGame(Game $game): void;

    public function playCard(Game $game, int $playerIndex, int $cardIndex): TurnResult;

    public function getStateForPlayer(Game $game, int $playerIndex): GameStateOutput;

    /**
     * Handle a capture choice (Scopa-specific, but on the interface for generic processor access).
     * Implementations that don't support this should throw \LogicException.
     */
    public function selectCapture(Game $game, int $optionIndex): TurnResult;

    /**
     * Start the next round (Scopa-specific multi-round games).
     * Implementations that don't support this should throw \LogicException.
     */
    public function nextRound(Game $game): void;
}
