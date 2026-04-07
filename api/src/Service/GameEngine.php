<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Output\GameStateOutput;
use App\Entity\Game;
use App\Enum\GameState;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\PendingPlay;
use App\ValueObject\SweepData;
use App\ValueObject\TurnResult;
use App\ValueObject\TurnResultType;

final class GameEngine
{
    public function __construct(
        private readonly DeckService $deckService,
        private readonly ScoringService $scoringService,
    ) {}

    public function initializeGame(Game $game): void
    {
        $deck = $this->deckService->createDeck();
        $deck = $this->deckService->shuffle($deck);
        $game->setDeck($deck);
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());
        $game->setPlayer1Scope(0);
        $game->setPlayer2Scope(0);
        $game->setLastCapturer(null);
        $game->setPendingPlay(null);
        $game->setTableCards(new CardCollection());
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection());
    }

    public function startRound(Game $game): void
    {
        $deck = $game->getDeck();

        ['taken' => $tableCards, 'remaining' => $deck] = $deck->take(4);
        $game->setTableCards($tableCards);

        ['taken' => $hand1, 'remaining' => $deck] = $deck->take(3);
        ['taken' => $hand2, 'remaining' => $deck] = $deck->take(3);
        $game->setPlayer1Hand($hand1);
        $game->setPlayer2Hand($hand2);

        $game->setDeck($deck);

        $game->setCurrentPlayer($game->getDealerIndex() === 0 ? 1 : 0);
        $game->setState(GameState::Playing);
    }

    public function dealHands(Game $game): void
    {
        $deck = $game->getDeck();
        if (count($deck) < 6) {
            return;
        }

        ['taken' => $hand1, 'remaining' => $deck] = $deck->take(3);
        ['taken' => $hand2, 'remaining' => $deck] = $deck->take(3);
        $game->setPlayer1Hand($hand1);
        $game->setPlayer2Hand($hand2);
        $game->setDeck($deck);
    }

    /**
     * Find all capture options for a given card on the table.
     * Single-card matches take priority over sum combinations.
     *
     * @return list<list<int>>
     */
    public function findCaptures(CardCollection $tableCards, Card $playedCard): array
    {
        $singleMatches = [];
        foreach ($tableCards as $i => $tc) {
            if ($tc->value === $playedCard->value) {
                $singleMatches[] = [$i];
            }
        }

        if (count($singleMatches) > 0) {
            return $singleMatches;
        }

        return $this->findSubsetsWithSum($tableCards, $playedCard->value);
    }

    /**
     * @return list<list<int>>
     */
    public function findSubsetsWithSum(CardCollection $tableCards, int $target): array
    {
        /** @var list<list<int>> $results */
        $results = [];
        $cards = $tableCards->toArray();
        $indices = array_keys($cards);

        $this->backtrack($cards, $indices, $target, 0, [], $results);

        return $results;
    }

    /**
     * @param list<Card> $cards
     * @param list<int> $indices
     * @param list<int> $current
     * @param list<list<int>> $results
     */
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
            $cardValue = $cards[$i]->value;
            if ($cardValue <= $remaining) {
                $current[] = $indices[$i];
                $this->backtrack($cards, $indices, $remaining - $cardValue, $i + 1, $current, $results);
                array_pop($current);
            }
        }
    }

    public function playCard(Game $game, int $playerIndex, int $cardIndex): TurnResult
    {
        $hand = $game->getPlayerHand($playerIndex);

        if ($cardIndex < 0 || $cardIndex >= count($hand)) {
            throw new \InvalidArgumentException('Invalid card index');
        }

        ['card' => $playedCard, 'remaining' => $hand] = $hand->removeAt($cardIndex);
        $game->setPlayerHand($playerIndex, $hand);

        $tableCards = $game->getTableCards();
        $captures = $this->findCaptures($tableCards, $playedCard);

        if (count($captures) === 0) {
            $game->setTableCards($tableCards->withAppended($playedCard));

            $sweep = $this->advanceTurn($game);

            return new TurnResult(
                type: TurnResultType::Place,
                card: $playedCard,
                playerIndex: $playerIndex,
                captured: new CardCollection(),
                scopa: false,
                sweep: $sweep,
            );
        }

        if (count($captures) === 1) {
            return $this->executeCapture($game, $playerIndex, $playedCard, $captures[0]);
        }

        // Multiple options: player must choose
        $game->setState(GameState::Choosing);
        $game->setPendingPlay(new PendingPlay(
            card: $playedCard,
            playerIndex: $playerIndex,
            options: $captures,
        ));

        return new TurnResult(
            type: TurnResultType::Choosing,
            card: $playedCard,
            playerIndex: $playerIndex,
            captured: new CardCollection(),
            scopa: false,
            options: $this->buildCaptureOptions($tableCards, $captures),
        );
    }

    public function selectCapture(Game $game, int $optionIndex): TurnResult
    {
        $pending = $game->getPendingPlay();
        if ($pending === null) {
            throw new \LogicException('No pending capture choice');
        }

        $options = $pending->options;
        if ($optionIndex < 0 || $optionIndex >= count($options)) {
            throw new \InvalidArgumentException('Invalid option index');
        }

        $game->setPendingPlay(null);

        return $this->executeCapture(
            $game,
            $pending->playerIndex,
            $pending->card,
            $options[$optionIndex]
        );
    }

    /** @param list<int> $captureIndices */
    private function executeCapture(Game $game, int $playerIndex, Card $playedCard, array $captureIndices): TurnResult
    {
        $tableCards = $game->getTableCards();
        $captured = $game->getPlayerCaptured($playerIndex);

        ['removed' => $capturedCards, 'remaining' => $tableCards] = $tableCards->removeIndices($captureIndices);

        $captured = $captured->withAppended($playedCard, ...$capturedCards->toArray());
        $game->setPlayerCaptured($playerIndex, $captured);

        $game->setTableCards($tableCards);
        $game->setLastCapturer($playerIndex);

        $isScopa = false;
        $isLastPlay = count($game->getPlayer1Hand()) === 0
            && count($game->getPlayer2Hand()) === 0
            && count($game->getDeck()) === 0;

        if (count($tableCards) === 0 && !$isLastPlay) {
            $isScopa = true;
            $game->setPlayerScope($playerIndex, $game->getPlayerScope($playerIndex) + 1);
        }

        $sweep = $this->advanceTurn($game);

        return new TurnResult(
            type: TurnResultType::Capture,
            card: $playedCard,
            playerIndex: $playerIndex,
            captured: $capturedCards,
            scopa: $isScopa,
            sweep: $sweep,
        );
    }

    private function advanceTurn(Game $game): ?SweepData
    {
        if (count($game->getPlayer1Hand()) === 0 && count($game->getPlayer2Hand()) === 0) {
            if (count($game->getDeck()) > 0) {
                $this->dealHands($game);
            } else {
                return $this->endRound($game);
            }
        }

        if ($game->getState() !== GameState::RoundEnd && $game->getState() !== GameState::GameOver) {
            $game->setCurrentPlayer($game->getCurrentPlayer() === 0 ? 1 : 0);
            $game->setState(GameState::Playing);
        }

        return null;
    }

    private function endRound(Game $game): SweepData
    {
        $remainingCards = $game->getTableCards();
        $lastCapturer = $game->getLastCapturer();

        if ($lastCapturer !== null && count($remainingCards) > 0) {
            $captured = $game->getPlayerCaptured($lastCapturer);
            $captured = $captured->withAppended(...$remainingCards->toArray());
            $game->setPlayerCaptured($lastCapturer, $captured);
            $game->setTableCards(new CardCollection());
        }

        $scores = $this->scoringService->scoreRound($game);

        $p1RoundTotal = $scores->player1->total();
        $p2RoundTotal = $scores->player2->total();

        $game->setPlayer1TotalScore($game->getPlayer1TotalScore() + $p1RoundTotal);
        $game->setPlayer2TotalScore($game->getPlayer2TotalScore() + $p2RoundTotal);

        $history = $game->getRoundHistory();
        $history[] = new \App\ValueObject\RoundHistoryEntry(
            scores: $scores,
            totals: [$game->getPlayer1TotalScore(), $game->getPlayer2TotalScore()],
        );
        $game->setRoundHistory($history);

        $s1 = $game->getPlayer1TotalScore();
        $s2 = $game->getPlayer2TotalScore();

        if (($s1 >= 11 || $s2 >= 11) && $s1 !== $s2) {
            $game->setState(GameState::GameOver);
        } else {
            $game->setState(GameState::RoundEnd);
        }

        return new SweepData(remainingCards: $remainingCards, lastCapturer: $lastCapturer);
    }

    public function nextRound(Game $game): void
    {
        $game->setDealerIndex($game->getDealerIndex() === 0 ? 1 : 0);
        $this->initializeGame($game);
        $this->startRound($game);
    }

    public function getStateForPlayer(Game $game, int $playerIndex): GameStateOutput
    {
        $opponentIndex = $playerIndex === 0 ? 1 : 0;

        /** @var list<list<Card>>|null $pendingChoice */
        $pendingChoice = null;
        if ($game->getState() === GameState::Choosing && $game->getPendingPlay() !== null) {
            $pending = $game->getPendingPlay();
            if ($pending->playerIndex === $playerIndex) {
                $pendingChoice = $this->buildCaptureOptions(
                    $game->getTableCards(),
                    $pending->options
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

    /**
     * @param list<list<int>> $options
     * @return list<list<Card>>
     */
    private function buildCaptureOptions(CardCollection $tableCards, array $options): array
    {
        $result = [];
        foreach ($options as $indices) {
            $cards = [];
            foreach ($indices as $idx) {
                $cards[] = $tableCards->get($idx);
            }
            $result[] = $cards;
        }
        return $result;
    }
}
