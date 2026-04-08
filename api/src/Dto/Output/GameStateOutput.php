<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Enum\DeckStyle;
use App\Enum\GameType;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\LastTrick;
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
        public ?string $mercureToken = null,
        public GameType $gameType = GameType::Scopa,
        public ?Card $briscolaCard = null,
        public ?LastTrick $lastTrick = null,
    ) {}

    public function withMercureToken(string $mercureToken): self
    {
        return new self(
            state: $this->state,
            currentPlayer: $this->currentPlayer,
            myIndex: $this->myIndex,
            myName: $this->myName,
            opponentName: $this->opponentName,
            myHand: $this->myHand,
            myCapturedCount: $this->myCapturedCount,
            myScope: $this->myScope,
            myTotalScore: $this->myTotalScore,
            opponentHandCount: $this->opponentHandCount,
            opponentCapturedCount: $this->opponentCapturedCount,
            opponentScope: $this->opponentScope,
            opponentTotalScore: $this->opponentTotalScore,
            table: $this->table,
            deckCount: $this->deckCount,
            isMyTurn: $this->isMyTurn,
            pendingChoice: $this->pendingChoice,
            roundHistory: $this->roundHistory,
            deckStyle: $this->deckStyle,
            turnResult: $this->turnResult,
            mercureToken: $mercureToken,
            gameType: $this->gameType,
            briscolaCard: $this->briscolaCard,
            lastTrick: $this->lastTrick,
        );
    }
}
