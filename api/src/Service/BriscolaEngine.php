<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Output\GameStateOutput;
use App\Entity\Game;
use App\Enum\GameState;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\LastTrick;
use App\ValueObject\TurnResult;
use App\ValueObject\TurnResultType;

final class BriscolaEngine implements GameEngine
{
    public function __construct(
        private readonly DeckService $deckService,
        private readonly BriscolaScoringService $scoringService,
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
        $game->setBriscolaCard(null);
        $game->setLastTrick(null);
        $game->setTrickLeader(null);
    }

    public function startGame(Game $game): void
    {
        $deck = $game->getDeck();

        // Deal 3 cards to each player
        ['taken' => $hand1, 'remaining' => $deck] = $deck->take(3);
        ['taken' => $hand2, 'remaining' => $deck] = $deck->take(3);
        $game->setPlayer1Hand($hand1);
        $game->setPlayer2Hand($hand2);

        // Reveal the briscola card (next card from deck) and place it at the end
        ['taken' => $briscolaCards, 'remaining' => $deck] = $deck->take(1);
        $briscolaCard = $briscolaCards->get(0);
        $game->setBriscolaCard($briscolaCard);

        // Put briscola at the end of the deck (it will be the last card drawn)
        $deck = $deck->withAppended($briscolaCard);
        $game->setDeck($deck);

        // Non-dealer leads first
        $leader = $game->getDealerIndex() === 0 ? 1 : 0;
        $game->setCurrentPlayer($leader);
        $game->setTrickLeader($leader);
        $game->setState(GameState::Playing);
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
        $trickLeader = $game->getTrickLeader();

        if (count($tableCards) === 0) {
            // This player is the leader — place card on table, wait for follower
            $game->setTableCards(new CardCollection([$playedCard]));
            $game->setTrickLeader($playerIndex);

            // Switch to the other player
            $follower = $playerIndex === 0 ? 1 : 0;
            $game->setCurrentPlayer($follower);
            $game->setState(GameState::Playing);

            return new TurnResult(
                type: TurnResultType::Place,
                card: $playedCard,
                playerIndex: $playerIndex,
                captured: new CardCollection(),
                scopa: false,
                cardIndex: $cardIndex,
            );
        }

        // This player is the follower — resolve the trick
        $leaderCard = $tableCards->get(0);
        $followerCard = $playedCard;
        $leaderIndex = $trickLeader ?? ($playerIndex === 0 ? 1 : 0);

        $winnerIndex = $this->resolveTrick($leaderCard, $followerCard, $leaderIndex, $game->getBriscolaCard());

        // Both cards go to winner's captured pile
        $captured = $game->getPlayerCaptured($winnerIndex);
        $captured = $captured->withAppended($leaderCard, $followerCard);
        $game->setPlayerCaptured($winnerIndex, $captured);

        // Clear the table
        $game->setTableCards(new CardCollection());

        // Store last trick for animation
        $game->setLastTrick(new LastTrick(
            leaderCard: $leaderCard,
            followerCard: $followerCard,
            winnerIndex: $winnerIndex,
        ));

        // Draw cards from deck
        $this->drawAfterTrick($game, $winnerIndex);

        // Check for game end
        if ($this->isGameOver($game)) {
            $this->endGame($game);
        } else {
            // Winner leads next trick
            $game->setCurrentPlayer($winnerIndex);
            $game->setTrickLeader($winnerIndex);
            $game->setState(GameState::Playing);
        }

        return new TurnResult(
            type: TurnResultType::Trick,
            card: $followerCard,
            playerIndex: $playerIndex,
            captured: new CardCollection([$leaderCard, $followerCard]),
            scopa: false,
            trickWinner: $winnerIndex,
            leaderCard: $leaderCard,
            cardIndex: $cardIndex,
        );
    }

    public function selectCapture(Game $game, int $optionIndex): TurnResult
    {
        throw new \LogicException('Briscola does not support capture selection');
    }

    public function nextRound(Game $game): void
    {
        throw new \LogicException('Briscola does not support multiple rounds');
    }

    public function getStateForPlayer(Game $game, int $playerIndex): GameStateOutput
    {
        $opponentIndex = $playerIndex === 0 ? 1 : 0;

        return new GameStateOutput(
            state: $game->getState()->value,
            currentPlayer: $game->getCurrentPlayer(),
            myIndex: $playerIndex,
            myName: $game->getPlayerName($playerIndex) ?? '',
            opponentName: $game->getPlayerName($opponentIndex) ?? '',
            myHand: $game->getPlayerHand($playerIndex),
            myCapturedCount: count($game->getPlayerCaptured($playerIndex)),
            myScope: 0,
            myTotalScore: $this->scoringService->countPoints($game->getPlayerCaptured($playerIndex)),
            opponentHandCount: count($game->getPlayerHand($opponentIndex)),
            opponentCapturedCount: count($game->getPlayerCaptured($opponentIndex)),
            opponentScope: 0,
            opponentTotalScore: $this->scoringService->countPoints($game->getPlayerCaptured($opponentIndex)),
            table: $game->getTableCards(),
            deckCount: count($game->getDeck()),
            isMyTurn: $game->getCurrentPlayer() === $playerIndex,
            roundHistory: $game->getRoundHistory(),
            deckStyle: $game->getDeckStyle(),
            gameType: $game->getGameType(),
            briscolaCard: $game->getBriscolaCard(),
            lastTrick: $game->getLastTrick(),
        );
    }

    /**
     * Resolve who wins a trick.
     * Rules: trump beats non-trump; same suit = higher strength wins; different non-trump suits = leader wins.
     */
    private function resolveTrick(Card $leaderCard, Card $followerCard, int $leaderIndex, ?Card $briscolaCard): int
    {
        $followerIndex = $leaderIndex === 0 ? 1 : 0;
        $trumpSuit = $briscolaCard?->suit;

        $leaderIsTrump = $trumpSuit !== null && $leaderCard->suit === $trumpSuit;
        $followerIsTrump = $trumpSuit !== null && $followerCard->suit === $trumpSuit;

        if ($leaderIsTrump && $followerIsTrump) {
            // Both trump: higher strength wins
            return $this->scoringService->getCardStrength($followerCard) > $this->scoringService->getCardStrength($leaderCard)
                ? $followerIndex : $leaderIndex;
        }

        if ($leaderIsTrump) {
            return $leaderIndex;
        }

        if ($followerIsTrump) {
            return $followerIndex;
        }

        // Neither is trump
        if ($leaderCard->suit === $followerCard->suit) {
            // Same suit: higher strength wins
            return $this->scoringService->getCardStrength($followerCard) > $this->scoringService->getCardStrength($leaderCard)
                ? $followerIndex : $leaderIndex;
        }

        // Different non-trump suits: leader wins
        return $leaderIndex;
    }

    /**
     * After each trick, both players draw one card. Winner draws first.
     * When deck is empty, no drawing. The briscola card is the last card in the deck.
     */
    private function drawAfterTrick(Game $game, int $winnerIndex): void
    {
        $deck = $game->getDeck();
        if (count($deck) === 0) {
            return;
        }

        $loserIndex = $winnerIndex === 0 ? 1 : 0;

        // Winner draws first
        ['taken' => $drawn, 'remaining' => $deck] = $deck->take(1);
        $winnerHand = $game->getPlayerHand($winnerIndex);
        $game->setPlayerHand($winnerIndex, $winnerHand->withAppended($drawn->get(0)));

        if (count($deck) > 0) {
            // Loser draws
            ['taken' => $drawn, 'remaining' => $deck] = $deck->take(1);
            $loserHand = $game->getPlayerHand($loserIndex);
            $game->setPlayerHand($loserIndex, $loserHand->withAppended($drawn->get(0)));
        }

        $game->setDeck($deck);
        // NOTE: Do NOT clear briscolaCard when deck empties. The briscola suit must
        // remain known for the rest of the game to resolve tricks correctly.
    }

    private function isGameOver(Game $game): bool
    {
        return count($game->getDeck()) === 0
            && count($game->getPlayer1Hand()) === 0
            && count($game->getPlayer2Hand()) === 0;
    }

    private function endGame(Game $game): void
    {
        $p1Points = $this->scoringService->countPoints($game->getPlayer1Captured());
        $p2Points = $this->scoringService->countPoints($game->getPlayer2Captured());

        $game->setPlayer1TotalScore($p1Points);
        $game->setPlayer2TotalScore($p2Points);

        $game->setState(GameState::GameOver);
    }
}
