<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Output\GameStateOutput;
use App\Entity\Game;
use App\Enum\GameState;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * @phpstan-import-type Card from Game
 * @phpstan-import-type ScoreRow from Game
 */
final class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {}

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

        $this->hub->publish($update);
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

    public function publishGameState(string $gameId, Game $game, GameEngine $engine): void
    {
        $state0 = $engine->getStateForPlayer($game, 0);
        $state1 = $engine->getStateForPlayer($game, 1);

        $this->publishToPlayer($gameId, 0, 'game-state', $this->stateToArray($state0));
        $this->publishToPlayer($gameId, 1, 'game-state', $this->stateToArray($state1));
    }

    /** @param list<list<Card>> $options */
    public function publishChooseCapture(string $gameId, int $playerIndex, array $options): void
    {
        $this->publishToPlayer($gameId, $playerIndex, 'choose-capture', [
            'options' => $options,
        ]);
    }

    /**
     * @param array{0: ScoreRow, 1: ScoreRow} $scores
     * @param array{remainingCards: list<Card>, lastCapturer: int|null}|null $sweep
     */
    public function publishRoundEnd(string $gameId, Game $game, GameEngine $engine, array $scores, ?array $sweep = null): void
    {
        $state0 = $engine->getStateForPlayer($game, 0);
        $state1 = $engine->getStateForPlayer($game, 1);

        $sweepData = $sweep ? ['remainingCards' => $sweep['remainingCards'], 'lastCapturer' => $sweep['lastCapturer']] : null;

        $this->publishToPlayer($gameId, 0, 'round-end', array_filter([
            'scores' => $scores,
            'gameState' => $this->stateToArray($state0),
            'sweep' => $sweepData,
        ], fn ($v) => $v !== null));
        $this->publishToPlayer($gameId, 1, 'round-end', array_filter([
            'scores' => $scores,
            'gameState' => $this->stateToArray($state1),
            'sweep' => $sweepData,
        ], fn ($v) => $v !== null));
    }

    /**
     * @param array{0: ScoreRow, 1: ScoreRow} $scores
     * @param array{remainingCards: list<Card>, lastCapturer: int|null}|null $sweep
     */
    public function publishGameOver(string $gameId, Game $game, GameEngine $engine, array $scores, ?array $sweep = null): void
    {
        $state0 = $engine->getStateForPlayer($game, 0);
        $state1 = $engine->getStateForPlayer($game, 1);

        $winner = $game->getPlayer1TotalScore() > $game->getPlayer2TotalScore() ? 0 : 1;
        $sweepData = $sweep ? ['remainingCards' => $sweep['remainingCards'], 'lastCapturer' => $sweep['lastCapturer']] : null;

        $this->publishToPlayer($gameId, 0, 'game-over', array_filter([
            'scores' => $scores,
            'winner' => $winner,
            'gameState' => $this->stateToArray($state0),
            'sweep' => $sweepData,
        ], fn ($v) => $v !== null));
        $this->publishToPlayer($gameId, 1, 'game-over', array_filter([
            'scores' => $scores,
            'winner' => $winner,
            'gameState' => $this->stateToArray($state1),
            'sweep' => $sweepData,
        ], fn ($v) => $v !== null));
    }

    /**
     * Publishes the complete turn outcome: turn-result followed by the appropriate
     * game state event (game-state, round-end, or game-over).
     *
     * @param array<string, mixed> $turnResult
     */
    public function publishTurnOutcome(string $gameId, Game $game, GameEngine $engine, array $turnResult): void
    {
        $this->publishToBothPlayers($gameId, 'turn-result', $turnResult);

        if ($game->getState() === GameState::RoundEnd || $game->getState() === GameState::GameOver) {
            $lastHistory = $game->getRoundHistory();
            $lastEntry = end($lastHistory);
            /** @var array{0: ScoreRow, 1: ScoreRow} $scores */
            $scores = \is_array($lastEntry) ? $lastEntry['scores'] : [];
            /** @var array{remainingCards: list<Card>, lastCapturer: int|null}|null $sweep */
            $sweep = $turnResult['sweep'] ?? null;
            if ($game->getState() === GameState::GameOver) {
                $this->publishGameOver($gameId, $game, $engine, $scores, $sweep);
            } else {
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
        return [
            'state' => $state->state,
            'currentPlayer' => $state->currentPlayer,
            'myIndex' => $state->myIndex,
            'myName' => $state->myName,
            'opponentName' => $state->opponentName,
            'myHand' => $state->myHand,
            'myCapturedCount' => $state->myCapturedCount,
            'myScope' => $state->myScope,
            'myTotalScore' => $state->myTotalScore,
            'opponentHandCount' => $state->opponentHandCount,
            'opponentCapturedCount' => $state->opponentCapturedCount,
            'opponentScope' => $state->opponentScope,
            'opponentTotalScore' => $state->opponentTotalScore,
            'table' => $state->table,
            'deckCount' => $state->deckCount,
            'isMyTurn' => $state->isMyTurn,
            'pendingChoice' => $state->pendingChoice,
            'roundHistory' => $state->roundHistory,
            'deckStyle' => $state->deckStyle,
        ];
    }
}
