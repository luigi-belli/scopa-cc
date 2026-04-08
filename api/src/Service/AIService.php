<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\ValueObject\AIMove;

interface AIService
{
    public function evaluateMove(Game $game, int $aiIndex): AIMove;

    /**
     * Auto-select a capture option when AI faces a choice (Scopa-specific).
     * Implementations that don't support this should throw \LogicException.
     */
    public function autoSelectCapture(Game $game): int;
}
