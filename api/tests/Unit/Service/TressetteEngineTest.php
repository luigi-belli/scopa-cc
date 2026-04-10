<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Enum\GameState;
use App\Enum\GameType;
use App\Enum\Suit;
use App\Service\DeckService;
use App\Service\TressetteEngine;
use App\Service\TressetteScoringService;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\TurnResultType;
use PHPUnit\Framework\TestCase;

class TressetteEngineTest extends TestCase
{
    private TressetteEngine $engine;
    private TressetteScoringService $scoring;

    protected function setUp(): void
    {
        $this->scoring = new TressetteScoringService();
        $this->engine = new TressetteEngine(new DeckService(), $this->scoring);
    }

    private function createTressetteGame(): Game
    {
        $game = new Game();
        $game->setGameType(GameType::Tressette);
        $game->setPlayer1Name('Alice');
        $game->setPlayer2Name('Bob');
        $game->setPlayer1Token('token1');
        $game->setPlayer2Token('token2');
        $game->setDealerIndex(0);
        $this->engine->initializeGame($game);
        $this->engine->startGame($game);
        return $game;
    }

    private function createManualGame(): Game
    {
        $game = new Game();
        $game->setGameType(GameType::Tressette);
        $game->setState(GameState::Playing);
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());
        return $game;
    }

    public function testInitializeGame(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Tressette);
        $this->engine->initializeGame($game);

        $this->assertCount(40, $game->getDeck());
        $this->assertCount(0, $game->getTableCards());
        $this->assertCount(0, $game->getPlayer1Hand());
        $this->assertCount(0, $game->getPlayer2Hand());
        $this->assertNull($game->getBriscolaCard());
        $this->assertNull($game->getLastTrick());
    }

    public function testStartGame_Deals10CardsEach(): void
    {
        $game = $this->createTressetteGame();

        $this->assertCount(10, $game->getPlayer1Hand());
        $this->assertCount(10, $game->getPlayer2Hand());
        $this->assertCount(20, $game->getDeck());
        $this->assertNull($game->getBriscolaCard());
        $this->assertEquals(GameState::Playing, $game->getState());
        // Non-dealer goes first (dealer is 0, so player 1 goes first)
        $this->assertEquals(1, $game->getCurrentPlayer());
    }

    public function testLeaderPlaysCard_PlacesOnTable(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(0);
        $game->setTrickLeader(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 3)]));
        $game->setTableCards(new CardCollection());
        $game->setDeck(new CardCollection());

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals(TurnResultType::Place, $result->type);
        $this->assertCount(1, $game->getTableCards());
        $this->assertEquals(1, $game->getCurrentPlayer());
    }

    public function testFollowerPlaysCard_SameSuit_HigherWins(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 1)])); // Ace beats 5
        $game->setDeck(new CardCollection());

        $result = $this->engine->playCard($game, 1, 0);

        $this->assertEquals(TurnResultType::Trick, $result->type);
        $this->assertEquals(1, $result->trickWinner);
        $this->assertCount(0, $game->getTableCards());
    }

    public function testFollowerPlaysCard_DifferentSuit_LeaderWins(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)])); // Weakest card
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 3)])); // Strongest, but different suit
        $game->setDeck(new CardCollection());

        $result = $this->engine->playCard($game, 1, 0);

        // Leader always wins with different suits (no trump in Tressette)
        $this->assertEquals(0, $result->trickWinner);
    }

    public function testTressetteStrengthOrder(): void
    {
        // 3 beats 2, 2 beats Asso, Asso beats Re
        $this->assertGreaterThan(
            $this->scoring->getCardStrength(new Card(Suit::Denari, 2)),
            $this->scoring->getCardStrength(new Card(Suit::Denari, 3))
        );
        $this->assertGreaterThan(
            $this->scoring->getCardStrength(new Card(Suit::Denari, 1)),
            $this->scoring->getCardStrength(new Card(Suit::Denari, 2))
        );
        $this->assertGreaterThan(
            $this->scoring->getCardStrength(new Card(Suit::Denari, 10)),
            $this->scoring->getCardStrength(new Card(Suit::Denari, 1))
        );
        // 4 is weakest
        $this->assertEquals(1, $this->scoring->getCardStrength(new Card(Suit::Denari, 4)));
    }

    public function testPointValues(): void
    {
        $this->assertEquals(3, $this->scoring->getCardPoints(new Card(Suit::Denari, 1)));  // Asso
        $this->assertEquals(1, $this->scoring->getCardPoints(new Card(Suit::Denari, 2)));  // Due
        $this->assertEquals(1, $this->scoring->getCardPoints(new Card(Suit::Denari, 3)));  // Tre
        $this->assertEquals(0, $this->scoring->getCardPoints(new Card(Suit::Denari, 4)));  // Zero
        $this->assertEquals(0, $this->scoring->getCardPoints(new Card(Suit::Denari, 7)));  // Zero
        $this->assertEquals(1, $this->scoring->getCardPoints(new Card(Suit::Denari, 8)));  // Fante
        $this->assertEquals(1, $this->scoring->getCardPoints(new Card(Suit::Denari, 9)));  // Cavallo
        $this->assertEquals(1, $this->scoring->getCardPoints(new Card(Suit::Denari, 10))); // Re
    }

    public function testCountPoints(): void
    {
        $cards = new CardCollection([
            new Card(Suit::Denari, 1),  // 3
            new Card(Suit::Coppe, 3),   // 1
            new Card(Suit::Bastoni, 10), // 1
            new Card(Suit::Spade, 7),    // 0
        ]);

        $this->assertEquals(5, $this->scoring->countPoints($cards));
    }

    public function testTotalCardPoints_Is32(): void
    {
        $deck = (new DeckService())->createDeck();
        $total = $this->scoring->countPoints($deck);
        $this->assertEquals(32, $total);
    }

    public function testDrawAfterTrick_WhenDeckHasCards(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 3)]));
        $game->setDeck(new CardCollection([
            new Card(Suit::Coppe, 6),
            new Card(Suit::Bastoni, 7),
            new Card(Suit::Coppe, 8),
            new Card(Suit::Spade, 9),
        ]));

        $this->engine->playCard($game, 1, 0);

        // Both players draw: winner first, then loser
        $this->assertCount(1, $game->getPlayer1Hand());
        $this->assertCount(1, $game->getPlayer2Hand());
        $this->assertCount(2, $game->getDeck());
    }

    public function testNoDrawWhenDeckEmpty(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Coppe, 5)]));
        $game->setPlayer2Hand(new CardCollection([
            new Card(Suit::Denari, 3),
            new Card(Suit::Coppe, 7),
        ]));
        $game->setDeck(new CardCollection()); // Empty stock — no drawing

        $this->engine->playCard($game, 1, 0);

        // No drawing when deck is empty
        $this->assertCount(1, $game->getPlayer1Hand());
        $this->assertCount(1, $game->getPlayer2Hand());
    }

    public function testFollowSuit_Required_EmptyDeck(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection());
        // Player 2 has a Denari card — must play it
        $game->setPlayer2Hand(new CardCollection([
            new Card(Suit::Coppe, 3),
            new Card(Suit::Denari, 7),
        ]));
        $game->setDeck(new CardCollection());

        // Playing Coppe when holding Denari should fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must follow suit');
        $this->engine->playCard($game, 1, 0);
    }

    public function testFollowSuit_Required_WithDeck(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection());
        // Player 2 has Denari — must follow suit even with cards in deck
        $game->setPlayer2Hand(new CardCollection([
            new Card(Suit::Coppe, 3),
            new Card(Suit::Denari, 7),
        ]));
        $game->setDeck(new CardCollection([new Card(Suit::Spade, 5), new Card(Suit::Spade, 6)]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Must follow suit');
        $this->engine->playCard($game, 1, 0);
    }

    public function testFollowSuit_AllowedWhenVoid(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection());
        // Player 2 has no Denari — can play anything
        $game->setPlayer2Hand(new CardCollection([
            new Card(Suit::Coppe, 3),
            new Card(Suit::Bastoni, 7),
        ]));
        $game->setDeck(new CardCollection());

        $result = $this->engine->playCard($game, 1, 0);
        $this->assertEquals(TurnResultType::Trick, $result->type);
        // Leader wins because follower played off-suit
        $this->assertEquals(0, $result->trickWinner);
    }

    public function testGameOver_AllCardsPlayed(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setDeck(new CardCollection());
        // Give player 1 some captured cards for scoring
        $game->setPlayer1Captured(new CardCollection([
            new Card(Suit::Denari, 1), // 3 points
            new Card(Suit::Coppe, 3),  // 1 point
        ]));

        $this->engine->playCard($game, 1, 0);

        $this->assertEquals(GameState::GameOver, $game->getState());
    }

    public function testUltimaBonus_LastTrickWinnerGetsBonus(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 3)])); // Tre beats 4
        $game->setDeck(new CardCollection());
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $result = $this->engine->playCard($game, 1, 0);

        // Player 2 wins the last trick
        $this->assertEquals(1, $result->trickWinner);
        $this->assertEquals(GameState::GameOver, $game->getState());

        // Player 2 gets card points (4=0, 3=1) + ultima bonus (3) = 4
        $this->assertEquals(4, $game->getPlayer2TotalScore());
        // Player 1 gets 0 card points + no ultima = 0
        $this->assertEquals(0, $game->getPlayer1TotalScore());
    }

    public function testUltimaBonus_ReflectedInGetStateForPlayer(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setPlayer1Name('Alice');
        $game->setPlayer2Name('Bob');
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 3)])); // Tre beats 4
        $game->setDeck(new CardCollection());
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $this->engine->playCard($game, 1, 0);
        $this->assertEquals(GameState::GameOver, $game->getState());

        // getStateForPlayer must include ultima bonus in scores
        $state0 = $this->engine->getStateForPlayer($game, 0);
        $state1 = $this->engine->getStateForPlayer($game, 1);

        // Player 2 won last trick: card points (4=0, 3=1) + ultima (3) = 4
        $this->assertEquals(0, $state0->myTotalScore);
        $this->assertEquals(4, $state0->opponentTotalScore);
        $this->assertEquals(4, $state1->myTotalScore);
        $this->assertEquals(0, $state1->opponentTotalScore);
    }

    public function testCardIntegrity_AllCardsAccountedFor(): void
    {
        $game = $this->createTressetteGame();

        // Play 10 cards (5 tricks), following suit when required
        for ($i = 0; $i < 10; $i++) {
            $playerIdx = $game->getCurrentPlayer();
            $hand = $game->getPlayerHand($playerIdx);
            if (count($hand) === 0) {
                break;
            }

            $cardIndex = 0;
            $tableCards = $game->getTableCards();
            if (count($tableCards) > 0) {
                $ledSuit = $tableCards->get(0)->suit;
                foreach ($hand as $idx => $card) {
                    if ($card->suit === $ledSuit) {
                        $cardIndex = $idx;
                        break;
                    }
                }
            }

            $this->engine->playCard($game, $playerIdx, $cardIndex);
        }

        $total = count($game->getDeck())
            + count($game->getTableCards())
            + count($game->getPlayer1Hand())
            + count($game->getPlayer2Hand())
            + count($game->getPlayer1Captured())
            + count($game->getPlayer2Captured());

        $this->assertEquals(40, $total, 'All 40 cards must be accounted for');
    }

    public function testGetStateForPlayer(): void
    {
        $game = $this->createTressetteGame();

        $state0 = $this->engine->getStateForPlayer($game, 0);
        $state1 = $this->engine->getStateForPlayer($game, 1);

        $this->assertCount(10, $state0->myHand);
        $this->assertEquals(10, $state0->opponentHandCount);
        $this->assertEquals(0, $state0->myIndex);
        $this->assertEquals(GameType::Tressette, $state0->gameType);
        $this->assertNull($state0->briscolaCard);
        $this->assertEquals(0, $state0->myScope);

        $this->assertCount(10, $state1->myHand);
        $this->assertEquals(1, $state1->myIndex);
    }

    public function testSelectCapture_Throws(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Tressette);

        $this->expectException(\LogicException::class);
        $this->engine->selectCapture($game, 0);
    }

    public function testNextRound_Throws(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Tressette);

        $this->expectException(\LogicException::class);
        $this->engine->nextRound($game);
    }

    public function testFullGame_20Tricks(): void
    {
        $game = $this->createTressetteGame();

        $trickCount = 0;
        while ($game->getState() === GameState::Playing) {
            $playerIdx = $game->getCurrentPlayer();
            $hand = $game->getPlayerHand($playerIdx);
            if (count($hand) === 0) {
                break;
            }

            // Must always follow suit — find a legal card
            $cardIndex = 0;
            $tableCards = $game->getTableCards();
            if (count($tableCards) > 0) {
                $ledSuit = $tableCards->get(0)->suit;
                foreach ($hand as $idx => $card) {
                    if ($card->suit === $ledSuit) {
                        $cardIndex = $idx;
                        break;
                    }
                }
            }

            $this->engine->playCard($game, $playerIdx, $cardIndex);
            if (count($game->getTableCards()) === 0) {
                $trickCount++;
            }
        }

        $this->assertEquals(GameState::GameOver, $game->getState());
        $this->assertEquals(20, $trickCount, 'Tressette should have exactly 20 tricks');

        // Total points should be 32 (card points) + 3 (ultima) = 35
        $total = $game->getPlayer1TotalScore() + $game->getPlayer2TotalScore();
        $this->assertEquals(35, $total, 'Total points must equal 35 (32 card + 3 ultima)');
    }

    public function testDrawnCards_IncludedInTurnResult(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 3)]));
        $winnerDraw = new Card(Suit::Coppe, 6);
        $loserDraw = new Card(Suit::Bastoni, 7);
        $game->setDeck(new CardCollection([$winnerDraw, $loserDraw]));

        $result = $this->engine->playCard($game, 1, 0);

        // Player 1 (index 1) wins with Tre > Quattro
        $this->assertEquals(1, $result->trickWinner);
        // Winner draws first card from deck, loser draws second
        $this->assertNotNull($result->winnerDrawnCard);
        $this->assertNotNull($result->loserDrawnCard);
        $this->assertTrue($winnerDraw->equals($result->winnerDrawnCard));
        $this->assertTrue($loserDraw->equals($result->loserDrawnCard));
    }

    public function testDrawnCards_NullWhenDeckEmpty(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 4)]));
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Coppe, 5)]));
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 3)]));
        $game->setDeck(new CardCollection());

        $result = $this->engine->playCard($game, 1, 0);

        $this->assertNull($result->winnerDrawnCard);
        $this->assertNull($result->loserDrawnCard);
    }

    public function testLastTrick_StoredForAnimation(): void
    {
        $game = $this->createManualGame();
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $leaderCard = new Card(Suit::Denari, 5);
        $followerCard = new Card(Suit::Denari, 3);
        $game->setTableCards(new CardCollection([$leaderCard]));
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Coppe, 4)]));
        $game->setPlayer2Hand(new CardCollection([$followerCard, new Card(Suit::Bastoni, 6)]));
        $game->setDeck(new CardCollection());

        $this->engine->playCard($game, 1, 0);

        $lastTrick = $game->getLastTrick();
        $this->assertNotNull($lastTrick);
        $this->assertTrue($leaderCard->equals($lastTrick->leaderCard));
        $this->assertTrue($followerCard->equals($lastTrick->followerCard));
        $this->assertEquals(1, $lastTrick->winnerIndex); // Tre beats 5
    }
}
