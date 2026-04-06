<?php

declare(strict_types=1);

namespace App\Dto\Output;

final readonly class GameStateOutput
{
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
