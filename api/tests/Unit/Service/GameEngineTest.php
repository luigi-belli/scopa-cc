<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Enum\GameState;
use App\Enum\Suit;
use App\Service\DeckService;
use App\Service\GameEngine;
use App\Service\ScoringService;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\PendingPlay;
use App\ValueObject\TurnResultType;
use PHPUnit\Framework\TestCase;

class GameEngineTest extends TestCase
{
    private GameEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new GameEngine(new DeckService(), new ScoringService());
    }

    private function createStartedGame(): Game
    {
        $game = new Game();
        $game->setPlayer1Name('Alice');
        $game->setPlayer2Name('Bob');
        $game->setPlayer1Token('token1');
        $game->setPlayer2Token('token2');
        $game->setDealerIndex(0);
        $this->engine->initializeGame($game);
        $this->engine->startRound($game);
        return $game;
    }

    public function testInitializeGame(): void
    {
        $game = new Game();
        $this->engine->initializeGame($game);

        $this->assertCount(40, $game->getDeck());
        $this->assertCount(0, $game->getTableCards());
        $this->assertCount(0, $game->getPlayer1Hand());
        $this->assertCount(0, $game->getPlayer2Hand());
    }

    public function testStartRound(): void
    {
        $game = $this->createStartedGame();

        $this->assertCount(4, $game->getTableCards());
        $this->assertCount(3, $game->getPlayer1Hand());
        $this->assertCount(3, $game->getPlayer2Hand());
        $this->assertCount(30, $game->getDeck());
        $this->assertEquals(GameState::Playing, $game->getState());
        $this->assertEquals(1, $game->getCurrentPlayer());
    }

    public function testDealHands(): void
    {
        $game = $this->createStartedGame();
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection());
        $deckBefore = count($game->getDeck());

        $this->engine->dealHands($game);

        $this->assertCount(3, $game->getPlayer1Hand());
        $this->assertCount(3, $game->getPlayer2Hand());
        $this->assertCount($deckBefore - 6, $game->getDeck());
    }

    public function testFindCaptures_SingleMatch(): void
    {
        $table = new CardCollection([
            new Card(Suit::Denari, 3),
            new Card(Suit::Coppe, 7),
            new Card(Suit::Bastoni, 5),
        ]);
        $card = new Card(Suit::Spade, 7);

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertCount(1, $captures);
        $this->assertEquals([1], $captures[0]);
    }

    public function testFindCaptures_MultipleSingleMatches(): void
    {
        $table = new CardCollection([
            new Card(Suit::Denari, 5),
            new Card(Suit::Coppe, 7),
            new Card(Suit::Bastoni, 5),
        ]);
        $card = new Card(Suit::Spade, 5);

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertCount(2, $captures);
        $this->assertEquals([0], $captures[0]);
        $this->assertEquals([2], $captures[1]);
    }

    public function testFindCaptures_SingleMatchPriority(): void
    {
        $table = new CardCollection([
            new Card(Suit::Denari, 7),
            new Card(Suit::Coppe, 3),
            new Card(Suit::Bastoni, 4),
        ]);
        $card = new Card(Suit::Spade, 7);

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertCount(1, $captures);
        $this->assertEquals([0], $captures[0]);
    }

    public function testFindCaptures_SumMatch(): void
    {
        $table = new CardCollection([
            new Card(Suit::Denari, 3),
            new Card(Suit::Coppe, 4),
            new Card(Suit::Bastoni, 2),
        ]);
        $card = new Card(Suit::Spade, 7);

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertCount(1, $captures);
        $this->assertContains(0, $captures[0]);
        $this->assertContains(1, $captures[0]);
    }

    public function testFindCaptures_MultipleSumMatches(): void
    {
        $table = new CardCollection([
            new Card(Suit::Denari, 3),
            new Card(Suit::Coppe, 7),
            new Card(Suit::Bastoni, 4),
            new Card(Suit::Spade, 6),
        ]);
        $card = new Card(Suit::Denari, 10);

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertCount(2, $captures);
    }

    public function testFindCaptures_NoMatch(): void
    {
        $table = new CardCollection([
            new Card(Suit::Denari, 2),
            new Card(Suit::Coppe, 3),
        ]);
        $card = new Card(Suit::Spade, 8);

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertEmpty($captures);
    }

    public function testPlayCard_Place(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 8)]));
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 1)]));
        $game->setTableCards(new CardCollection([new Card(Suit::Bastoni, 2)]));
        $game->setDeck(CardCollection::fill(30, new Card(Suit::Denari, 1)));

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals(TurnResultType::Place, $result->type);
        $this->assertCount(2, $game->getTableCards());
        $this->assertCount(0, $game->getPlayer1Hand());
    }

    public function testPlayCard_AutoCapture(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 1)]));
        $game->setTableCards(new CardCollection([
            new Card(Suit::Bastoni, 5),
            new Card(Suit::Spade, 3),
        ]));
        $game->setDeck(CardCollection::fill(30, new Card(Suit::Denari, 1)));

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals(TurnResultType::Capture, $result->type);
        $this->assertCount(1, $game->getTableCards());
        $this->assertCount(2, $game->getPlayer1Captured());
    }

    public function testPlayCard_Choosing(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 1)]));
        $game->setTableCards(new CardCollection([
            new Card(Suit::Bastoni, 5),
            new Card(Suit::Spade, 5),
        ]));
        $game->setDeck(CardCollection::fill(30, new Card(Suit::Denari, 1)));

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals(TurnResultType::Choosing, $result->type);
        $this->assertEquals(GameState::Choosing, $game->getState());
        $this->assertNotNull($game->getPendingPlay());
    }

    public function testPlayCard_Scopa(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 1)]));
        $game->setTableCards(new CardCollection([new Card(Suit::Bastoni, 5)]));
        $game->setDeck(CardCollection::fill(30, new Card(Suit::Denari, 1)));

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals(TurnResultType::Capture, $result->type);
        $this->assertTrue($result->scopa);
        $this->assertEquals(1, $game->getPlayer1Scope());
    }

    public function testPlayCard_LastPlayNoScopa(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setPlayer2Hand(new CardCollection());
        $game->setTableCards(new CardCollection([new Card(Suit::Bastoni, 5)]));
        $game->setDeck(new CardCollection());
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection([new Card(Suit::Coppe, 1)]));

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals(TurnResultType::Capture, $result->type);
        $this->assertFalse($result->scopa);
        $this->assertEquals(0, $game->getPlayer1Scope());
    }

    public function testSelectCapture(): void
    {
        $game = new Game();
        $game->setState(GameState::Choosing);
        $game->setCurrentPlayer(0);
        $game->setTableCards(new CardCollection([
            new Card(Suit::Bastoni, 5),
            new Card(Suit::Spade, 5),
            new Card(Suit::Coppe, 3),
        ]));
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Denari, 1)]));
        $game->setDeck(CardCollection::fill(30, new Card(Suit::Denari, 1)));
        $game->setPendingPlay(new PendingPlay(
            card: new Card(Suit::Denari, 5),
            playerIndex: 0,
            options: [[0], [1]],
        ));

        $result = $this->engine->selectCapture($game, 1);

        $this->assertEquals(TurnResultType::Capture, $result->type);
        $this->assertNull($game->getPendingPlay());
        $this->assertCount(2, $game->getTableCards());
    }

    public function testEndRound_LastCapturerGetsRemaining(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 8)]));
        $game->setPlayer2Hand(new CardCollection());
        $game->setDeck(new CardCollection());
        $game->setTableCards(new CardCollection([
            new Card(Suit::Bastoni, 2),
            new Card(Suit::Coppe, 3),
        ]));
        $game->setLastCapturer(1);
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $this->engine->playCard($game, 0, 0);

        $this->assertCount(0, $game->getTableCards());
        $this->assertCount(3, $game->getPlayer2Captured());
    }

    public function testGetStateForPlayer(): void
    {
        $game = $this->createStartedGame();

        $state0 = $this->engine->getStateForPlayer($game, 0);
        $state1 = $this->engine->getStateForPlayer($game, 1);

        $this->assertCount(3, $state0->myHand);
        $this->assertEquals(3, $state0->opponentHandCount);
        $this->assertEquals(0, $state0->myIndex);
        $this->assertEquals('Alice', $state0->myName);
        $this->assertEquals('Bob', $state0->opponentName);

        $this->assertCount(3, $state1->myHand);
        $this->assertEquals(3, $state1->opponentHandCount);
        $this->assertEquals(1, $state1->myIndex);
        $this->assertEquals('Bob', $state1->myName);
        $this->assertEquals('Alice', $state1->opponentName);
    }

    public function testReDealWhenHandsEmpty(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setDealerIndex(0);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 8)]));
        $game->setPlayer2Hand(new CardCollection());
        $game->setTableCards(new CardCollection([new Card(Suit::Coppe, 2)]));
        $game->setDeck(CardCollection::fill(12, new Card(Suit::Bastoni, 1)));
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $this->engine->playCard($game, 0, 0);

        $this->assertCount(3, $game->getPlayer1Hand());
        $this->assertCount(3, $game->getPlayer2Hand());
        $this->assertCount(6, $game->getDeck());
    }

    public function testNoDuplicateCardsOnTable(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 3)]));
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 1)]));
        $game->setTableCards(new CardCollection([
            new Card(Suit::Bastoni, 5),
            new Card(Suit::Spade, 7),
        ]));
        $game->setDeck(CardCollection::fill(30, new Card(Suit::Bastoni, 1)));
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $this->engine->playCard($game, 0, 0);

        $table = $game->getTableCards();
        $keys = array_map(fn($c) => $c->suit->value . '-' . $c->value, $table->toArray());
        $this->assertCount(count($keys), array_unique($keys), 'Table should have no duplicate cards');
    }

    public function testNoDuplicateCardsAfterCapture(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Denari, 5)]));
        $game->setPlayer2Hand(new CardCollection([new Card(Suit::Coppe, 1)]));
        $game->setTableCards(new CardCollection([
            new Card(Suit::Bastoni, 5),
            new Card(Suit::Spade, 7),
        ]));
        $game->setDeck(CardCollection::fill(30, new Card(Suit::Bastoni, 1)));
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals(TurnResultType::Capture, $result->type);
        foreach ($game->getTableCards() as $tc) {
            $this->assertFalse(
                $tc->suit === Suit::Bastoni && $tc->value === 5,
                'Captured card must not remain on the table'
            );
        }
        foreach ($game->getTableCards() as $tc) {
            $this->assertFalse(
                $tc->suit === Suit::Denari && $tc->value === 5,
                'Played card must not remain on the table after capture'
            );
        }
        $this->assertCount(1, $game->getTableCards());
    }

    public function testDeckIntegrity_AllCardsAccountedFor(): void
    {
        $game = $this->createStartedGame();

        for ($turn = 0; $turn < 6; $turn++) {
            $playerIdx = $game->getCurrentPlayer();
            $hand = $game->getPlayerHand($playerIdx);
            if (count($hand) === 0) {
                break;
            }
            $result = $this->engine->playCard($game, $playerIdx, 0);
            if ($result->type === TurnResultType::Choosing) {
                $this->engine->selectCapture($game, 0);
            }
        }

        $total = count($game->getDeck())
            + count($game->getTableCards())
            + count($game->getPlayer1Hand())
            + count($game->getPlayer2Hand())
            + count($game->getPlayer1Captured())
            + count($game->getPlayer2Captured());

        $this->assertEquals(40, $total, 'All 40 cards must be accounted for at all times');
    }

    public function testTableCardsUniqueAfterMultiplePlays(): void
    {
        $game = $this->createStartedGame();

        for ($turn = 0; $turn < 6; $turn++) {
            $playerIdx = $game->getCurrentPlayer();
            $hand = $game->getPlayerHand($playerIdx);
            if (count($hand) === 0) {
                break;
            }

            $this->engine->playCard($game, $playerIdx, 0);
            if ($game->getState() === GameState::Choosing) {
                $this->engine->selectCapture($game, 0);
            }

            $table = $game->getTableCards();
            $keys = array_map(fn($c) => $c->suit->value . '-' . $c->value, $table->toArray());
            $this->assertCount(
                count($keys),
                array_unique($keys),
                "Duplicate card on table after turn $turn"
            );
        }
    }
}
