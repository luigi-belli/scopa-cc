<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Enum\DeckStyle;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\RoundHistoryEntry;

final readonly class GameStateOutput
{
    /**
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
        public CardCollection $myHand,
        public int $myCapturedCount,
        public int $myScope,
        public int $myTotalScore,
        public int $opponentHandCount,
        public int $opponentCapturedCount,
        public int $opponentScope,
        public int $opponentTotalScore,
        public CardCollection $table,
        public int $deckCount,
        public bool $isMyTurn,
        public ?array $pendingChoice = null,
        public array $roundHistory = [],
        public DeckStyle $deckStyle = DeckStyle::Piacentine,
        public ?array $turnResult = null,
    ) {}
}
