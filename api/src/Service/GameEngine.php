<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Output\GameStateOutput;
use App\Entity\Game;
use App\Enum\GameState;

final class GameEngine
{
    public function __construct(
        private readonly DeckService $deckService,
        private readonly ScoringService $scoringService,
    ) {}

    public function initializeGame(Game $game): void
    {
        $deck = $this->deckService->createDeck();
        $this->deckService->shuffle($deck);
        $game->setDeck($deck);
        $game->setPlayer1Captured([]);
        $game->setPlayer2Captured([]);
        $game->setPlayer1Scope(0);
        $game->setPlayer2Scope(0);
        $game->setLastCapturer(null);
        $game->setPendingPlay(null);
        $game->setTableCards([]);
        $game->setPlayer1Hand([]);
        $game->setPlayer2Hand([]);
    }

    public function startRound(Game $game): void
    {
        $deck = $game->getDeck();

        // Deal 4 cards to table
        $tableCards = array_splice($deck, 0, 4);
        $game->setTableCards($tableCards);

        // Deal 3 cards to each player
        $hand1 = array_splice($deck, 0, 3);
        $hand2 = array_splice($deck, 0, 3);
        $game->setPlayer1Hand($hand1);
        $game->setPlayer2Hand($hand2);

        $game->setDeck($deck);

        // Non-dealer goes first
        $game->setCurrentPlayer($game->getDealerIndex() === 0 ? 1 : 0);
        $game->setState(GameState::Playing);
    }

    public function dealHands(Game $game): void
    {
        $deck = $game->getDeck();
        if (count($deck) < 6) {
            return;
        }

        $hand1 = array_splice($deck, 0, 3);
        $hand2 = array_splice($deck, 0, 3);
        $game->setPlayer1Hand($hand1);
        $game->setPlayer2Hand($hand2);
        $game->setDeck($deck);
    }

    /**
     * Find all capture options for a given card on the table.
     * Returns array of options, each option is an array of table card indices.
     * Single-card matches take priority over sum combinations.
     */
    public function findCaptures(array $tableCards, array $playedCard): array
    {
        $playedValue = $playedCard['value'];

        // First: find single-card matches (priority rule)
        $singleMatches = [];
        foreach ($tableCards as $i => $tc) {
            if ($tc['value'] === $playedValue) {
                $singleMatches[] = [$i];
            }
        }

        if (count($singleMatches) > 0) {
            return $singleMatches;
        }

        // No single match: find sum combinations
        return $this->findSubsetsWithSum($tableCards, $playedValue);
    }

    /**
     * Find all subsets of table cards that sum to the target value.
     * Returns array of arrays of indices.
     */
    public function findSubsetsWithSum(array $tableCards, int $target): array
    {
        $results = [];
        $indices = array_keys($tableCards);
        $values = array_values($tableCards);

        $this->backtrack($values, $indices, $target, 0, [], $results);

        return $results;
    }

    private function backtrack(array $cards, array $indices, int $remaining, int $start, array $current, array &$results): void
    {
        if ($remaining === 0 && count($current) >= 2) {
            $results[] = $current;
            return;
        }
        if ($remaining <= 0) {
            return;
        }

        for ($i = $start; $i < count($cards); $i++) {
            $cardValue = $cards[$i]['value'];
            if ($cardValue <= $remaining) {
                $current[] = $indices[$i];
                $this->backtrack($cards, $indices, $remaining - $cardValue, $i + 1, $current, $results);
                array_pop($current);
            }
        }
    }

    /**
     * Play a card from a player's hand.
     * Returns: ['type' => 'place'|'capture'|'choosing', 'card' => ..., 'captured' => [...], 'scopa' => bool, 'options' => [...]]
     */
    public function playCard(Game $game, int $playerIndex, int $cardIndex): array
    {
        $hand = $game->getPlayerHand($playerIndex);

        if ($cardIndex < 0 || $cardIndex >= count($hand)) {
            throw new \InvalidArgumentException('Invalid card index');
        }

        $playedCard = $hand[$cardIndex];
        array_splice($hand, $cardIndex, 1);
        $game->setPlayerHand($playerIndex, $hand);

        $tableCards = $game->getTableCards();
        $captures = $this->findCaptures($tableCards, $playedCard);

        if (count($captures) === 0) {
            // Place card on table
            $tableCards[] = $playedCard;
            $game->setTableCards($tableCards);

            $sweep = $this->advanceTurn($game);

            return [
                'type' => 'place',
                'card' => $playedCard,
                'playerIndex' => $playerIndex,
                'captured' => [],
                'scopa' => false,
                'sweep' => $sweep,
            ];
        }

        if (count($captures) === 1) {
            // Auto-capture
            return $this->executeCapture($game, $playerIndex, $playedCard, $captures[0]);
        }

        // Multiple options: player must choose
        $game->setState(GameState::Choosing);
        $game->setPendingPlay([
            'card' => $playedCard,
            'playerIndex' => $playerIndex,
            'options' => $captures,
        ]);

        return [
            'type' => 'choosing',
            'card' => $playedCard,
            'playerIndex' => $playerIndex,
            'options' => $this->buildCaptureOptions($tableCards, $captures),
            'captured' => [],
            'scopa' => false,
        ];
    }

    public function selectCapture(Game $game, int $optionIndex): array
    {
        $pending = $game->getPendingPlay();
        if ($pending === null) {
            throw new \LogicException('No pending capture choice');
        }

        $options = $pending['options'];
        if ($optionIndex < 0 || $optionIndex >= count($options)) {
            throw new \InvalidArgumentException('Invalid option index');
        }

        $game->setPendingPlay(null);

        return $this->executeCapture(
            $game,
            $pending['playerIndex'],
            $pending['card'],
            $options[$optionIndex]
        );
    }

    private function executeCapture(Game $game, int $playerIndex, array $playedCard, array $captureIndices): array
    {
        $tableCards = $game->getTableCards();
        $captured = $game->getPlayerCaptured($playerIndex);

        // Collect captured cards
        $capturedCards = [];
        foreach ($captureIndices as $idx) {
            $capturedCards[] = $tableCards[$idx];
        }

        // Add played card + captured cards to player's captured pile
        $captured[] = $playedCard;
        foreach ($capturedCards as $card) {
            $captured[] = $card;
        }
        $game->setPlayerCaptured($playerIndex, $captured);

        // Remove captured cards from table (reverse sort to maintain indices)
        rsort($captureIndices);
        foreach ($captureIndices as $idx) {
            array_splice($tableCards, $idx, 1);
        }
        $game->setTableCards($tableCards);
        $game->setLastCapturer($playerIndex);

        // Check for scopa
        $isScopa = false;
        $isLastPlay = count($game->getPlayer1Hand()) === 0
            && count($game->getPlayer2Hand()) === 0
            && count($game->getDeck()) === 0;

        if (count($tableCards) === 0 && !$isLastPlay) {
            $isScopa = true;
            $game->setPlayerScope($playerIndex, $game->getPlayerScope($playerIndex) + 1);
        }

        $sweep = $this->advanceTurn($game);

        return [
            'type' => 'capture',
            'card' => $playedCard,
            'playerIndex' => $playerIndex,
            'captured' => $capturedCards,
            'scopa' => $isScopa,
            'sweep' => $sweep,
        ];
    }

    private function advanceTurn(Game $game): ?array
    {
        // Check if both hands are empty
        if (count($game->getPlayer1Hand()) === 0 && count($game->getPlayer2Hand()) === 0) {
            if (count($game->getDeck()) > 0) {
                // Re-deal
                $this->dealHands($game);
            } else {
                // Round over — return sweep data for the caller
                return $this->endRound($game);
            }
        }

        if ($game->getState() !== GameState::RoundEnd && $game->getState() !== GameState::GameOver) {
            $game->setCurrentPlayer($game->getCurrentPlayer() === 0 ? 1 : 0);
            $game->setState(GameState::Playing);
        }

        return null;
    }

    /**
     * @return array{remainingCards: list<array{suit: string, value: int}>, lastCapturer: int|null}
     */
    private function endRound(Game $game): array
    {
        // Save pre-sweep state for animation
        $remainingCards = $game->getTableCards();
        $lastCapturer = $game->getLastCapturer();

        // Last capturer gets remaining table cards
        if ($lastCapturer !== null && count($remainingCards) > 0) {
            $captured = $game->getPlayerCaptured($lastCapturer);
            foreach ($remainingCards as $card) {
                $captured[] = $card;
            }
            $game->setPlayerCaptured($lastCapturer, $captured);
            $game->setTableCards([]);
        }

        // Score the round
        $scores = $this->scoringService->scoreRound($game);

        $p1RoundTotal = $this->scoringService->totalRoundScore($scores[0]);
        $p2RoundTotal = $this->scoringService->totalRoundScore($scores[1]);

        $game->setPlayer1TotalScore($game->getPlayer1TotalScore() + $p1RoundTotal);
        $game->setPlayer2TotalScore($game->getPlayer2TotalScore() + $p2RoundTotal);

        // Add to round history
        $history = $game->getRoundHistory();
        $history[] = [
            'scores' => $scores,
            'totals' => [
                $game->getPlayer1TotalScore(),
                $game->getPlayer2TotalScore(),
            ],
        ];
        $game->setRoundHistory($history);

        // Check win condition
        $s1 = $game->getPlayer1TotalScore();
        $s2 = $game->getPlayer2TotalScore();

        if (($s1 >= 11 || $s2 >= 11) && $s1 !== $s2) {
            $game->setState(GameState::GameOver);
        } else {
            $game->setState(GameState::RoundEnd);
        }

        return ['remainingCards' => $remainingCards, 'lastCapturer' => $lastCapturer];
    }

    public function nextRound(Game $game): void
    {
        // Alternate dealer
        $game->setDealerIndex($game->getDealerIndex() === 0 ? 1 : 0);
        $this->initializeGame($game);
        $this->startRound($game);
    }

    public function getStateForPlayer(Game $game, int $playerIndex): GameStateOutput
    {
        $opponentIndex = $playerIndex === 0 ? 1 : 0;

        $pendingChoice = null;
        if ($game->getState() === GameState::Choosing && $game->getPendingPlay() !== null) {
            $pending = $game->getPendingPlay();
            if ($pending['playerIndex'] === $playerIndex) {
                $pendingChoice = $this->buildCaptureOptions(
                    $game->getTableCards(),
                    $pending['options']
                );
            }
        }

        return new GameStateOutput(
            state: $game->getState()->value,
            currentPlayer: $game->getCurrentPlayer(),
            myIndex: $playerIndex,
            myName: $game->getPlayerName($playerIndex) ?? '',
            opponentName: $game->getPlayerName($opponentIndex) ?? '',
            myHand: $game->getPlayerHand($playerIndex),
            myCapturedCount: count($game->getPlayerCaptured($playerIndex)),
            myScope: $game->getPlayerScope($playerIndex),
            myTotalScore: $game->getPlayerTotalScore($playerIndex),
            opponentHandCount: count($game->getPlayerHand($opponentIndex)),
            opponentCapturedCount: count($game->getPlayerCaptured($opponentIndex)),
            opponentScope: $game->getPlayerScope($opponentIndex),
            opponentTotalScore: $game->getPlayerTotalScore($opponentIndex),
            table: $game->getTableCards(),
            deckCount: count($game->getDeck()),
            isMyTurn: $game->getCurrentPlayer() === $playerIndex,
            pendingChoice: $pendingChoice,
            roundHistory: $game->getRoundHistory(),
            deckStyle: $game->getDeckStyle(),
        );
    }

    private function buildCaptureOptions(array $tableCards, array $options): array
    {
        $result = [];
        foreach ($options as $indices) {
            $cards = [];
            foreach ($indices as $idx) {
                if (isset($tableCards[$idx])) {
                    $cards[] = $tableCards[$idx];
                }
            }
            $result[] = $cards;
        }
        return $result;
    }
}
