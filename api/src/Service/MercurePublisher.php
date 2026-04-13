<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Output\GameStateOutput;
use App\Entity\Game;
use App\Enum\GameState;
use App\Enum\GameType;
use App\ValueObject\Card;
use App\ValueObject\RoundHistoryEntry;
use App\ValueObject\RoundScores;
use App\ValueObject\SweepData;
use App\ValueObject\TurnResult;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercurePublisher
{
    /** @var list<Update> */
    private array $pendingUpdates = [];
    private bool $deferring = false;

    public function __construct(
        private readonly HubInterface $hub,
    ) {}

    public function startDeferring(): void
    {
        $this->pendingUpdates = [];
        $this->deferring = true;
    }

    public function flushDeferred(): void
    {
        $updates = $this->pendingUpdates;
        $this->pendingUpdates = [];
        $this->deferring = false;

        foreach ($updates as $update) {
            $this->hub->publish($update);
        }
    }

    /** @param array<string, mixed> $data */
    public function publishToPlayer(string $gameId, int $playerIndex, string $eventType, array $data): void
    {
        $topic = "/games/{$gameId}/player/{$playerIndex}";
        $update = new Update(
            $topic,
            json_encode([
                'type' => $eventType,
                'data' => $data,
            ], \JSON_THROW_ON_ERROR),
            false,
            null,
            $eventType,
        );

        if ($this->deferring) {
            $this->pendingUpdates[] = $update;
        } else {
            $this->hub->publish($update);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $data2
     */
    public function publishToBothPlayers(string $gameId, string $eventType, array $data, ?array $data2 = null): void
    {
        $this->publishToPlayer($gameId, 0, $eventType, $data);
        $this->publishToPlayer($gameId, 1, $eventType, $data2 ?? $data);
    }

    public function publishGameStateToPlayer(string $gameId, int $playerIndex, Game $game, GameEngine $engine): void
    {
        $state = $engine->getStateForPlayer($game, $playerIndex);
        $this->publishToPlayer($gameId, $playerIndex, 'game-state', $this->stateToArray($state));
    }

    public function publishGameState(string $gameId, Game $game, GameEngine $engine): void
    {
        $state0 = $engine->getStateForPlayer($game, 0);
        $state1 = $engine->getStateForPlayer($game, 1);

        $this->publishToPlayer($gameId, 0, 'game-state', $this->stateToArray($state0));
        $this->publishToPlayer($gameId, 1, 'game-state', $this->stateToArray($state1));
    }

    public function publishRoundEnd(string $gameId, Game $game, GameEngine $engine, RoundScores $scores, ?SweepData $sweep = null): void
    {
        $state0 = $engine->getStateForPlayer($game, 0);
        $state1 = $engine->getStateForPlayer($game, 1);

        $sweepData = $sweep?->jsonSerialize();

        $this->publishToPlayer($gameId, 0, 'round-end', array_filter([
            'scores' => $scores->jsonSerialize(),
            'gameState' => $this->stateToArray($state0),
            'sweep' => $sweepData,
        ], static fn($v) => $v !== null));
        $this->publishToPlayer($gameId, 1, 'round-end', array_filter([
            'scores' => $scores->jsonSerialize(),
            'gameState' => $this->stateToArray($state1),
            'sweep' => $sweepData,
        ], static fn($v) => $v !== null));
    }

    public function publishGameOver(string $gameId, Game $game, GameEngine $engine, ?RoundScores $scores, ?SweepData $sweep = null): void
    {
        $state0 = $engine->getStateForPlayer($game, 0);
        $state1 = $engine->getStateForPlayer($game, 1);

        $winner = $game->getResolvedWinner()
            ?? ($game->getPlayer1TotalScore() > $game->getPlayer2TotalScore() ? 0 : 1);
        $sweepData = $sweep?->jsonSerialize();

        // Include captured cards for trick-taking games so the frontend can show point breakdown
        $capturedCards = null;
        if ($game->getGameType() === GameType::Briscola || $game->getGameType() === GameType::Tressette) {
            $capturedCards = [
                $game->getPlayer1Captured()->jsonSerialize(),
                $game->getPlayer2Captured()->jsonSerialize(),
            ];
        }

        $this->publishToPlayer($gameId, 0, 'game-over', array_filter([
            'scores' => $scores?->jsonSerialize(),
            'winner' => $winner,
            'gameState' => $this->stateToArray($state0),
            'sweep' => $sweepData,
            'capturedCards' => $capturedCards,
        ], static fn($v) => $v !== null));
        $this->publishToPlayer($gameId, 1, 'game-over', array_filter([
            'scores' => $scores?->jsonSerialize(),
            'winner' => $winner,
            'gameState' => $this->stateToArray($state1),
            'sweep' => $sweepData,
            'capturedCards' => $capturedCards,
        ], static fn($v) => $v !== null));
    }

    public function publishTurnOutcome(string $gameId, Game $game, GameEngine $engine, TurnResult $turnResult): void
    {
        $this->publishToBothPlayers($gameId, 'turn-result', $turnResult->jsonSerialize());

        if ($game->getState() === GameState::RoundEnd || $game->getState() === GameState::GameOver) {
            $lastHistory = $game->getRoundHistory();
            $lastEntry = array_last($lastHistory);
            $scores = $lastEntry instanceof RoundHistoryEntry ? $lastEntry->scores : null;
            $sweep = $turnResult->sweep;
            if ($game->getState() === GameState::GameOver) {
                $this->publishGameOver($gameId, $game, $engine, $scores, $sweep);
            } elseif ($scores !== null) {
                $this->publishRoundEnd($gameId, $game, $engine, $scores, $sweep);
            }
        } else {
            $this->publishGameState($gameId, $game, $engine);
        }
    }

    public function publishOpponentDisconnected(string $gameId, int $playerIndex): void
    {
        $this->publishToPlayer($gameId, $playerIndex, 'opponent-disconnected', []);
    }

    /** @return array<string, mixed> */
    private function stateToArray(GameStateOutput $state): array
    {
        $pendingChoice = null;
        if ($state->pendingChoice !== null) {
            $pendingChoice = array_map(
                static fn(array $cards): array => array_map(
                    static fn(Card $c): array => $c->jsonSerialize(),
                    $cards,
                ),
                $state->pendingChoice,
            );
        }

        return [
            'state' => $state->state,
            'currentPlayer' => $state->currentPlayer,
            'myIndex' => $state->myIndex,
            'myName' => $state->myName,
            'opponentName' => $state->opponentName,
            'myHand' => $state->myHand->jsonSerialize(),
            'myCapturedCount' => $state->myCapturedCount,
            'myScope' => $state->myScope,
            'myTotalScore' => $state->myTotalScore,
            'opponentHandCount' => $state->opponentHandCount,
            'opponentCapturedCount' => $state->opponentCapturedCount,
            'opponentScope' => $state->opponentScope,
            'opponentTotalScore' => $state->opponentTotalScore,
            'table' => $state->table->jsonSerialize(),
            'deckCount' => $state->deckCount,
            'isMyTurn' => $state->isMyTurn,
            'pendingChoice' => $pendingChoice,
            'roundHistory' => array_map(
                static fn(RoundHistoryEntry $e): array => $e->jsonSerialize(),
                $state->roundHistory,
            ),
            'deckStyle' => $state->deckStyle->value,
            'gameType' => $state->gameType->value,
            'briscolaCard' => $state->briscolaCard?->jsonSerialize(),
            'lastTrick' => $state->lastTrick?->jsonSerialize(),
        ];
    }
}
