<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Enum\GameState;
use App\Enum\Suit;
use App\Service\DeckService;
use App\Service\ScopaEngine;
use App\Service\ScopaScoringService;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\PendingPlay;
use App\ValueObject\TurnResultType;
use PHPUnit\Framework\TestCase;

class GameEngineTest extends TestCase
{
    private ScopaEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new ScopaEngine(new DeckService(), new ScopaScoringService());
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
        $this->engine->startGame($game);
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

    /**
     * When both players reach 11+ but one reached it first by counting
     * order (Carte → Denari → Settebello → Primiera → Scope), the game
     * should end with that player as the winner.
     *
     * Scenario: both at 10 before the round. P1 gets carte (1pt, total 11),
     * P2 gets settebello (1pt, total 11). P1 reaches 11 first via carte.
     */
    public function testTiebreak_SequentialCounting_P1WinsViaCarte(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setDealerIndex(0);
        $game->setPlayer1TotalScore(10);
        $game->setPlayer2TotalScore(10);

        // P1 has more cards (22 vs 18) → P1 gets carte (+1)
        // P2 has the 7 of Denari → P2 gets settebello (+1)
        // Denari tied (5-5), primiera null for both (neither has 4 suits), scope 0-0.
        // Both get exactly 1 round point → 11-11 tie.
        // Sequential counting: carte counted first → P1 reaches 11 before P2.
        //
        // P1 pre-sweep: 21 cards in 3 suits (D1-5, C1-10, B1-6)
        // P1 plays B7 → swept back → 22 cards total, still 3 suits (D,C,B)
        // P2: 18 cards in 3 suits (D6-10 incl 7d, B8-10, S1-10)
        // Total: 40 cards.
        $p1Cards = [];
        for ($i = 1; $i <= 5; $i++) {
            $p1Cards[] = new Card(Suit::Denari, $i);
        }
        for ($i = 1; $i <= 10; $i++) {
            $p1Cards[] = new Card(Suit::Coppe, $i);
        }
        for ($i = 1; $i <= 6; $i++) {
            $p1Cards[] = new Card(Suit::Bastoni, $i);
        }

        $p2Cards = [];
        for ($i = 6; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Denari, $i); // includes D7 (settebello)
        }
        for ($i = 8; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Bastoni, $i);
        }
        for ($i = 1; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Spade, $i);
        }

        $game->setPlayer1Captured(new CardCollection($p1Cards));
        $game->setPlayer2Captured(new CardCollection($p2Cards));
        $game->setLastCapturer(0);

        // P1 plays Bastoni 7 (same suit as existing B1-6) onto empty table.
        // lastCapturer=P1 so it sweeps back to P1.
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Bastoni, 7)]));
        $game->setPlayer2Hand(new CardCollection());
        $game->setTableCards(new CardCollection());
        $game->setDeck(new CardCollection());

        $this->engine->playCard($game, 0, 0);

        $this->assertSame(GameState::GameOver, $game->getState());
        $this->assertSame(0, $game->getResolvedWinner());
    }

    /**
     * When both reach 11+ on the exact same category with the same running
     * total (e.g. both cross 11 via scope), there's no tiebreaker possible
     * and another round should start.
     *
     * Scenario: Pre-round P1=9, P2=9. P2 wins carte (+1), P1 wins settebello (+1),
     * denari tied, primiera null for both (neither has 4 suits), both have 1 scope.
     * Sequential counting:
     *   After carte:     P1=9,  P2=10
     *   After denari:    P1=9,  P2=10
     *   After settebello: P1=10, P2=10
     *   After primiera:  P1=10, P2=10
     *   After scope:     P1=11, P2=11 → still tied → RoundEnd
     */
    public function testTiebreak_StillTied_PlaysAnotherRound(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setDealerIndex(0);
        $game->setPlayer1TotalScore(9);
        $game->setPlayer2TotalScore(9);

        // Neither player has all 4 suits → both primiera = null.
        // P1 pre-sweep: D1-4,D7 (5 denari incl 7d), C1-10, B1-3 = 18 cards (3 suits)
        // P2: D5,D6,D8-10 (5 denari), B5-10, S1-10 = 21 cards (3 suits)
        // P1 plays B4 → swept back → P1=19, P2=21, total=40.
        $p1Cards = [];
        $p1Cards[] = new Card(Suit::Denari, 1);
        $p1Cards[] = new Card(Suit::Denari, 2);
        $p1Cards[] = new Card(Suit::Denari, 3);
        $p1Cards[] = new Card(Suit::Denari, 4);
        $p1Cards[] = new Card(Suit::Denari, 7); // settebello
        for ($i = 1; $i <= 10; $i++) {
            $p1Cards[] = new Card(Suit::Coppe, $i);
        }
        for ($i = 1; $i <= 3; $i++) {
            $p1Cards[] = new Card(Suit::Bastoni, $i);
        }
        // P1 pre-sweep: 18 cards, 3 suits (Denari, Coppe, Bastoni)

        $p2Cards = [];
        $p2Cards[] = new Card(Suit::Denari, 5);
        $p2Cards[] = new Card(Suit::Denari, 6);
        $p2Cards[] = new Card(Suit::Denari, 8);
        $p2Cards[] = new Card(Suit::Denari, 9);
        $p2Cards[] = new Card(Suit::Denari, 10);
        for ($i = 5; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Bastoni, $i);
        }
        for ($i = 1; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Spade, $i);
        }
        // P2: 21 cards, 3 suits (Denari, Bastoni, Spade)

        $game->setPlayer1Captured(new CardCollection($p1Cards));
        $game->setPlayer2Captured(new CardCollection($p2Cards));
        $game->setPlayer1Scope(1);
        $game->setPlayer2Scope(1);
        $game->setLastCapturer(0);

        // P1 plays Bastoni 4 (a suit P1 already has) onto empty table.
        // lastCapturer=P1 so it returns to P1 after sweep.
        // After endRound: P1=19 cards (3 suits), P2=21 cards (3 suits).
        // Carte: P2 wins (21>19). Denari: tied (5-5). Settebello: P1 (has 7d).
        // Primiera: null for both (neither has 4 suits). Scope: 1-1 each.
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Bastoni, 4)]));
        $game->setPlayer2Hand(new CardCollection());
        $game->setTableCards(new CardCollection());
        $game->setDeck(new CardCollection());

        $this->engine->playCard($game, 0, 0);

        // Both cross 11 on scope simultaneously → another round
        $this->assertSame(GameState::RoundEnd, $game->getState());
        $this->assertNull($game->getResolvedWinner());
    }

    /**
     * Standard case: one player clearly ahead at 11+, no tiebreaker needed.
     */
    public function testEndRound_ClearWinner_NoTiebreaker(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setDealerIndex(0);
        $game->setPlayer1TotalScore(10);
        $game->setPlayer2TotalScore(5);

        // P1 has more cards and settebello → gets at least 2 points → 12
        $p1Cards = [];
        for ($i = 1; $i <= 7; $i++) {
            $p1Cards[] = new Card(Suit::Denari, $i);
        }
        for ($i = 1; $i <= 10; $i++) {
            $p1Cards[] = new Card(Suit::Coppe, $i);
        }
        for ($i = 1; $i <= 4; $i++) {
            $p1Cards[] = new Card(Suit::Bastoni, $i);
        }
        // P1 pre-sweep: 21 cards (D1-7, C1-10, B1-4), 3 suits

        $p2Cards = [];
        for ($i = 8; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Denari, $i);
        }
        for ($i = 6; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Bastoni, $i);
        }
        for ($i = 1; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Spade, $i);
        }
        // P2: 18 cards (D8-10, B6-10, S1-10), 3 suits

        $game->setPlayer1Captured(new CardCollection($p1Cards));
        $game->setPlayer2Captured(new CardCollection($p2Cards));
        $game->setLastCapturer(0);

        // P1 plays B5 (not a duplicate). After sweep: P1=22, P2=18. Total=40.
        // Carte: P1(+1), Denari: P1(+1), Settebello: P1(+1). P1=13, P2=5.
        $game->setPlayer1Hand(new CardCollection([new Card(Suit::Bastoni, 5)]));
        $game->setPlayer2Hand(new CardCollection());
        $game->setTableCards(new CardCollection());
        $game->setDeck(new CardCollection());

        $this->engine->playCard($game, 0, 0);

        $this->assertSame(GameState::GameOver, $game->getState());
        $this->assertNull($game->getResolvedWinner()); // No tiebreaker needed
    }
}
