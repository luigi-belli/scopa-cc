<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Output\GameStateOutput;
use App\Entity\Game;
use App\Enum\GameState;
use App\Enum\Suit;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\LastTrick;
use App\ValueObject\TurnResult;
use App\ValueObject\TurnResultType;

final class TressetteEngine implements GameEngine
{
    private const int HAND_SIZE = 10;

    public function __construct(
        private readonly DeckService $deckService,
        private readonly TressetteScoringService $scoringService,
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

        // Deal 10 cards to each player
        ['taken' => $hand1, 'remaining' => $deck] = $deck->take(self::HAND_SIZE);
        ['taken' => $hand2, 'remaining' => $deck] = $deck->take(self::HAND_SIZE);
        $game->setPlayer1Hand($hand1);
        $game->setPlayer2Hand($hand2);
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

            $follower = $playerIndex === 0 ? 1 : 0;
            $game->setCurrentPlayer($follower);
            $game->setState(GameState::Playing);

            return new TurnResult(
                type: TurnResultType::Place,
                card: $playedCard,
                playerIndex: $playerIndex,
                captured: new CardCollection(),
                scopa: false,
            );
        }

        // This player is the follower — validate follow-suit in phase 2
        $leaderCard = $tableCards->get(0);
        $leaderIndex = $trickLeader ?? ($playerIndex === 0 ? 1 : 0);

        if ($this->mustFollowSuit($game)
            && $playedCard->suit !== $leaderCard->suit
            && $this->hasSuit($hand->withAppended($playedCard), $leaderCard->suit)
        ) {
            throw new \InvalidArgumentException('Must follow suit when possible');
        }

        $followerCard = $playedCard;
        $winnerIndex = $this->resolveTrick($leaderCard, $followerCard, $leaderIndex);

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

        // Draw cards from stock (phase 1 only)
        $this->drawAfterTrick($game, $winnerIndex);

        // Check for game end
        if ($this->isGameOver($game)) {
            $this->endGame($game, $winnerIndex);
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
        );
    }

    public function selectCapture(Game $game, int $optionIndex): TurnResult
    {
        throw new \LogicException('Tressette does not support capture selection');
    }

    public function nextRound(Game $game): void
    {
        throw new \LogicException('Tressette does not support multiple rounds');
    }

    public function getStateForPlayer(Game $game, int $playerIndex): GameStateOutput
    {
        $opponentIndex = $playerIndex === 0 ? 1 : 0;

        // After game over, use stored totals (which include the ultima bonus)
        if ($game->getState() === GameState::GameOver) {
            $myScore = $playerIndex === 0 ? $game->getPlayer1TotalScore() : $game->getPlayer2TotalScore();
            $opponentScore = $opponentIndex === 0 ? $game->getPlayer1TotalScore() : $game->getPlayer2TotalScore();
        } else {
            $myScore = $this->scoringService->countPoints($game->getPlayerCaptured($playerIndex));
            $opponentScore = $this->scoringService->countPoints($game->getPlayerCaptured($opponentIndex));
        }

        return new GameStateOutput(
            state: $game->getState()->value,
            currentPlayer: $game->getCurrentPlayer(),
            myIndex: $playerIndex,
            myName: $game->getPlayerName($playerIndex) ?? '',
            opponentName: $game->getPlayerName($opponentIndex) ?? '',
            myHand: $game->getPlayerHand($playerIndex),
            myCapturedCount: count($game->getPlayerCaptured($playerIndex)),
            myScope: 0,
            myTotalScore: $myScore,
            opponentHandCount: count($game->getPlayerHand($opponentIndex)),
            opponentCapturedCount: count($game->getPlayerCaptured($opponentIndex)),
            opponentScope: 0,
            opponentTotalScore: $opponentScore,
            table: $game->getTableCards(),
            deckCount: count($game->getDeck()),
            isMyTurn: $game->getCurrentPlayer() === $playerIndex,
            roundHistory: $game->getRoundHistory(),
            deckStyle: $game->getDeckStyle(),
            gameType: $game->getGameType(),
            briscolaCard: null,
            lastTrick: $game->getLastTrick(),
        );
    }

    /**
     * Resolve who wins a trick.
     * Same suit: higher strength wins. Different suits: leader always wins (no trump).
     */
    private function resolveTrick(Card $leaderCard, Card $followerCard, int $leaderIndex): int
    {
        $followerIndex = $leaderIndex === 0 ? 1 : 0;

        if ($leaderCard->suit === $followerCard->suit) {
            return $this->scoringService->getCardStrength($followerCard) > $this->scoringService->getCardStrength($leaderCard)
                ? $followerIndex : $leaderIndex;
        }

        // Different suits: leader always wins (no trump in Tressette)
        return $leaderIndex;
    }

    /**
     * After each trick in phase 1, both players draw one card. Winner draws first.
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
            ['taken' => $drawn, 'remaining' => $deck] = $deck->take(1);
            $loserHand = $game->getPlayerHand($loserIndex);
            $game->setPlayerHand($loserIndex, $loserHand->withAppended($drawn->get(0)));
        }

        $game->setDeck($deck);
    }

    /**
     * Whether the follower must follow suit (phase 2: stock is empty).
     */
    private function mustFollowSuit(Game $game): bool
    {
        return count($game->getDeck()) === 0;
    }

    private function hasSuit(CardCollection $hand, Suit $suit): bool
    {
        foreach ($hand as $card) {
            if ($card->suit === $suit) {
                return true;
            }
        }
        return false;
    }

    private function isGameOver(Game $game): bool
    {
        return count($game->getDeck()) === 0
            && count($game->getPlayer1Hand()) === 0
            && count($game->getPlayer2Hand()) === 0;
    }

    /**
     * End the game. The winner of the last trick gets the ultima bonus.
     */
    private function endGame(Game $game, int $lastTrickWinner): void
    {
        $p1Points = $this->scoringService->countPoints($game->getPlayer1Captured());
        $p2Points = $this->scoringService->countPoints($game->getPlayer2Captured());

        // Ultima bonus: last trick winner gets +3 points
        if ($lastTrickWinner === 0) {
            $p1Points += TressetteScoringService::ULTIMA_BONUS;
        } else {
            $p2Points += TressetteScoringService::ULTIMA_BONUS;
        }

        $game->setPlayer1TotalScore($p1Points);
        $game->setPlayer2TotalScore($p2Points);

        $game->setState(GameState::GameOver);
    }
}
