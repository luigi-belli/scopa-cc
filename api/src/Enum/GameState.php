<?php

declare(strict_types=1);

namespace App\Enum;

enum GameState: string
{
    case Waiting = 'waiting';
    case Playing = 'playing';
    case Choosing = 'choosing';
    case RoundEnd = 'round-end';
    case GameOver = 'game-over';
    case Finished = 'finished';

    /** Whether the game has ended (game-over or finished). */
    public function isTerminal(): bool
    {
        return $this === self::GameOver || $this === self::Finished;
    }
}
