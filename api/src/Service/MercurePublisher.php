<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Output\GameStateOutput;
use App\Entity\Game;
use App\Enum\GameState;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {}

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

    public function publishChooseCapture(string $gameId, int $playerIndex, array $options): void
    {
        $this->publishToPlayer($gameId, $playerIndex, 'choose-capture', [
            'options' => $options,
        ]);
    }

    public function publishRoundEnd(string $gameId, Game $game, GameEngine $engine, array $scores): void
    {
        $state0 = $engine->getStateForPlayer($game, 0);
        $state1 = $engine->getStateForPlayer($game, 1);

        $this->publishToPlayer($gameId, 0, 'round-end', [
            'scores' => $scores,
            'gameState' => $this->stateToArray($state0),
        ]);
        $this->publishToPlayer($gameId, 1, 'round-end', [
            'scores' => $scores,
            'gameState' => $this->stateToArray($state1),
        ]);
    }

    public function publishGameOver(string $gameId, Game $game, GameEngine $engine, array $scores): void
    {
        $state0 = $engine->getStateForPlayer($game, 0);
        $state1 = $engine->getStateForPlayer($game, 1);

        $winner = $game->getPlayer1TotalScore() > $game->getPlayer2TotalScore() ? 0 : 1;

        $this->publishToPlayer($gameId, 0, 'game-over', [
            'scores' => $scores,
            'winner' => $winner,
            'gameState' => $this->stateToArray($state0),
        ]);
        $this->publishToPlayer($gameId, 1, 'game-over', [
            'scores' => $scores,
            'winner' => $winner,
            'gameState' => $this->stateToArray($state1),
        ]);
    }

    /**
     * Publishes the complete turn outcome: turn-result followed by the appropriate
     * game state event (game-state, round-end, or game-over).
     */
    public function publishTurnOutcome(string $gameId, Game $game, GameEngine $engine, array $turnResult): void
    {
        $this->publishToBothPlayers($gameId, 'turn-result', $turnResult);

        if ($game->getState() === GameState::RoundEnd || $game->getState() === GameState::GameOver) {
            $lastHistory = $game->getRoundHistory();
            $lastEntry = end($lastHistory);
            $scores = \is_array($lastEntry) ? ($lastEntry['scores'] ?? []) : [];
            if ($game->getState() === GameState::GameOver) {
                $this->publishGameOver($gameId, $game, $engine, $scores);
            } else {
                $this->publishRoundEnd($gameId, $game, $engine, $scores);
            }
        } else {
            $this->publishGameState($gameId, $game, $engine);
        }
    }

    public function publishOpponentDisconnected(string $gameId, int $playerIndex): void
    {
        $this->publishToPlayer($gameId, $playerIndex, 'opponent-disconnected', []);
    }

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
