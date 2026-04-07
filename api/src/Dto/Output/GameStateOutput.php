<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Game;

/**
 * @phpstan-import-type Card from Game
 * @phpstan-import-type RoundHistoryEntry from Game
 */
final readonly class GameStateOutput
{
    /**
     * @param list<Card> $myHand
     * @param list<Card> $table
     * @param list<list<Card>>|null $pendingChoice
     * @param list<RoundHistoryEntry> $roundHistory
     * @param array<string, mixed>|null $turnResult
     */
    public function __construct(
        public string $state,
        public int $currentPlayer,
        public int $myIndex,
        public string $myName,
        public string $opponentName,
        public array $myHand,
        public int $myCapturedCount,
        public int $myScope,
        public int $myTotalScore,
        public int $opponentHandCount,
        public int $opponentCapturedCount,
        public int $opponentScope,
        public int $opponentTotalScore,
        public array $table,
        public int $deckCount,
        public bool $isMyTurn,
        public ?array $pendingChoice = null,
        public array $roundHistory = [],
        public string $deckStyle = 'piacentine',
        public ?array $turnResult = null,
    ) {}
}
