<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Enum\GameState;
use App\Enum\GameType;
use App\Enum\Suit;
use App\Service\BriscolaEngine;
use App\Service\BriscolaScoringService;
use App\Service\DeckService;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\TurnResultType;
use PHPUnit\Framework\TestCase;

class BriscolaEngineTest extends TestCase
{
    private BriscolaEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new BriscolaEngine(new DeckService(), new BriscolaScoringService());
    }

    private function createBriscolaGame(): Game
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $game->setPlayer1Name('Alice');
        $game->setPlayer2Name('Bob');
        $game->setPlayer1Token('token1');
        $game->setPlayer2Token('token2');
        $game->setDealerIndex(0);
        $this->engine->initializeGame($game);
        $this->engine->startGame($game);
        return $game;
    }

    public function testInitializeGame(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $this->engine->initializeGame($game);

        $this->assertCount(40, $game->getDeck());
        $this->assertCount(0, $game->getTableCards());
        $this->assertCount(0, $game->getPlayer1Hand());
        $this->assertCount(0, $game->getPlayer2Hand());
        $this->assertNull($game->getBriscolaCard());
    }

    public function testStartGame(): void
    {
        $game = $this->createBriscolaGame();

        $this->assertCount(3, $game->getPlayer1Hand());
        $this->assertCount(3, $game->getPlayer2Hand());
        $this->assertNotNull($game->getBriscolaCard());
        // 40 - 6 (dealt) - 1 (briscola revealed) + 1 (briscola at end of deck) = 34
        $this->assertCount(34, $game->getDeck());
        $this->assertEquals(GameState::Playing, $game->getState());
        // Non-dealer goes first (dealer is 0, so player 1 goes first)
        $this->assertEquals(1, $game->getCurrentPlayer());
    }

    public function testBriscolaCardIsLastInDeck(): void
    {
        $game = $this->createBriscolaGame();
        $briscola = $game->getBriscolaCard();
        $deck = $game->getDeck();

        $this->assertNotNull($briscola);
        $lastCard = $deck->get(count($deck) - 1);
        $this->assertTrue($briscola->equals($lastCard));
    }

    public function testLeaderPlaysCard_PlacesOnTable(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setTrickLeader(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 3)]));
        $game->setTableCards(new CardCollection());
        $game->setDeck(new CardCollection());
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals(TurnResultType::Place, $result->type);
        $this->assertCount(1, $game->getTableCards());
        $this->assertEquals(1, $game->getCurrentPlayer()); // Follower's turn
    }

    public function testFollowerPlaysCard_ResolvesTrick(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setBriscolaCard(new Card(Suit::Spade, 3)); // Spade is trump
        // Leader already played Denari 5
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 1)])); // Ace of Denari beats 5
        $game->setDeck(new CardCollection());
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $result = $this->engine->playCard($game, 1, 0);

        $this->assertEquals(TurnResultType::Trick, $result->type);
        $this->assertEquals(1, $result->trickWinner); // Ace beats 5 in same suit
        $this->assertCount(0, $game->getTableCards());
    }

    public function testTrumpBeatsNonTrump(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setBriscolaCard(new Card(Suit::Spade, 3)); // Spade is trump
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 1)])); // Leader: Ace of Denari
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Spade, 2)])); // Follower: 2 of trump
        $game->setDeck(new CardCollection());
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $result = $this->engine->playCard($game, 1, 0);

        $this->assertEquals(1, $result->trickWinner); // Trump 2 beats non-trump Ace
    }

    public function testDifferentNonTrumpSuits_LeaderWins(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setBriscolaCard(new Card(Suit::Spade, 3)); // Spade is trump
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 2)])); // Leader: 2 of Denari
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 1)])); // Follower: Ace of Coppe
        $game->setDeck(new CardCollection());
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $result = $this->engine->playCard($game, 1, 0);

        $this->assertEquals(0, $result->trickWinner); // Leader wins: different non-trump suits
    }

    public function testDrawAfterTrick(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $briscolaCard = new Card(Suit::Spade, 3);
        $game->setBriscolaCard($briscolaCard);
        // Leader played, follower about to play
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 2)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        // Deck has 4 cards (including briscola at end)
        $game->setDeck(new CardCollection([
            new Card(Suit::Coppe, 6),
            new Card(Suit::Bastoni, 7),
            new Card(Suit::Coppe, 8),
            $briscolaCard,
        ]));
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $this->engine->playCard($game, 1, 0);

        // Winner draws first, then loser. Both should have 1 card each.
        $this->assertCount(1, $game->getPlayer1Hand());
        $this->assertCount(1, $game->getPlayer2Hand());
        $this->assertCount(2, $game->getDeck());
    }

    public function testBriscolaCardRetainedWhenDeckEmpty(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $briscolaCard = new Card(Suit::Spade, 3);
        $game->setBriscolaCard($briscolaCard);
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 2)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        // Deck has exactly 2 cards (last draw)
        $game->setDeck(new CardCollection([
            new Card(Suit::Coppe, 6),
            $briscolaCard,
        ]));
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $this->engine->playCard($game, 1, 0);

        $this->assertCount(0, $game->getDeck());
        $this->assertNotNull($game->getBriscolaCard()); // Briscola must persist for trick resolution
        $this->assertEquals(Suit::Spade, $game->getBriscolaCard()->suit);
    }

    public function testTrumpSuitRecognizedAfterDeckExhausted(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $briscolaCard = new Card(Suit::Spade, 3);
        $game->setBriscolaCard($briscolaCard);
        // Deck empty — last cards already drawn
        $game->setDeck(new CardCollection());
        // Leader (p0) played a non-trump card
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 1)])); // Asso di Denari (11pts)
        $game->setPlayer1Hand(new CardCollection());
        // Follower (p1) plays a low trump card — should WIN because trump beats non-trump
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Spade, 2)])); // 2 di Spade (trump)
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $result = $this->engine->playCard($game, 1, 0);

        // Player 1 (follower) must win the trick with trump, even with deck empty
        $this->assertEquals(1, $result->trickWinner);
    }

    public function testGameOverAfterAllTricks(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(1);
        $game->setTrickLeader(0);
        $game->setBriscolaCard(new Card(Suit::Spade, 3)); // Must persist even with empty deck
        $game->setTableCards(new CardCollection([new Card(Suit::Denari, 2)]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setDeck(new CardCollection());
        $game->setPlayer1Captured(new CardCollection([
            new Card(Suit::Denari, 1), // 11 points
        ]));
        $game->setPlayer2Captured(new CardCollection());

        $this->engine->playCard($game, 1, 0);

        $this->assertEquals(GameState::GameOver, $game->getState());
        $this->assertGreaterThan(0, $game->getPlayer1TotalScore());
    }

    public function testCardIntegrity_AllCardsAccountedFor(): void
    {
        $game = $this->createBriscolaGame();

        // Play 6 cards (3 tricks worth)
        for ($i = 0; $i < 6; $i++) {
            $playerIdx = $game->getCurrentPlayer();
            $hand = $game->getPlayerHand($playerIdx);
            if (count($hand) === 0) {
                break;
            }
            $this->engine->playCard($game, $playerIdx, 0);
        }

        // Briscola card is in the deck (last position), not counted separately
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
        $game = $this->createBriscolaGame();

        $state0 = $this->engine->getStateForPlayer($game, 0);
        $state1 = $this->engine->getStateForPlayer($game, 1);

        $this->assertCount(3, $state0->myHand);
        $this->assertEquals(3, $state0->opponentHandCount);
        $this->assertEquals(0, $state0->myIndex);
        $this->assertEquals(GameType::Briscola, $state0->gameType);
        $this->assertNotNull($state0->briscolaCard);
        $this->assertEquals(0, $state0->myScope); // Briscola doesn't use scope

        $this->assertCount(3, $state1->myHand);
        $this->assertEquals(1, $state1->myIndex);
    }

    public function testSelectCapture_ThrowsForBriscola(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);

        $this->expectException(\LogicException::class);
        $this->engine->selectCapture($game, 0);
    }

    public function testNextRound_ThrowsForBriscola(): void
    {
        $game = new Game();
        $game->setGameType(GameType::Briscola);

        $this->expectException(\LogicException::class);
        $this->engine->nextRound($game);
    }

    public function testPointScoring(): void
    {
        $scoring = new BriscolaScoringService();

        $this->assertEquals(11, $scoring->getCardPoints(new Card(Suit::Denari, 1)));  // Ace
        $this->assertEquals(10, $scoring->getCardPoints(new Card(Suit::Denari, 3)));  // Three
        $this->assertEquals(4, $scoring->getCardPoints(new Card(Suit::Denari, 10)));  // King
        $this->assertEquals(3, $scoring->getCardPoints(new Card(Suit::Denari, 9)));   // Knight
        $this->assertEquals(2, $scoring->getCardPoints(new Card(Suit::Denari, 8)));   // Jack
        $this->assertEquals(0, $scoring->getCardPoints(new Card(Suit::Denari, 7)));   // Zero
        $this->assertEquals(0, $scoring->getCardPoints(new Card(Suit::Denari, 2)));   // Zero
    }

    public function testCountPoints(): void
    {
        $scoring = new BriscolaScoringService();

        $cards = new CardCollection([
            new Card(Suit::Denari, 1),  // 11
            new Card(Suit::Coppe, 3),   // 10
            new Card(Suit::Bastoni, 10), // 4
            new Card(Suit::Spade, 7),    // 0
        ]);

        $this->assertEquals(25, $scoring->countPoints($cards));
    }

    public function testCardStrength(): void
    {
        $scoring = new BriscolaScoringService();

        // Ace is strongest
        $this->assertGreaterThan(
            $scoring->getCardStrength(new Card(Suit::Denari, 3)),
            $scoring->getCardStrength(new Card(Suit::Denari, 1))
        );

        // Three is second strongest
        $this->assertGreaterThan(
            $scoring->getCardStrength(new Card(Suit::Denari, 10)),
            $scoring->getCardStrength(new Card(Suit::Denari, 3))
        );

        // Two is weakest
        $this->assertEquals(1, $scoring->getCardStrength(new Card(Suit::Denari, 2)));
    }
}
