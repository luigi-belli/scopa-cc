<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Enum\GameState;
use App\Service\DeckService;
use App\Service\GameEngine;
use App\Service\ScoringService;
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
        $this->assertEmpty($game->getTableCards());
        $this->assertEmpty($game->getPlayer1Hand());
        $this->assertEmpty($game->getPlayer2Hand());
    }

    public function testStartRound(): void
    {
        $game = $this->createStartedGame();

        $this->assertCount(4, $game->getTableCards());
        $this->assertCount(3, $game->getPlayer1Hand());
        $this->assertCount(3, $game->getPlayer2Hand());
        $this->assertCount(30, $game->getDeck());
        $this->assertEquals(GameState::Playing, $game->getState());
        // Non-dealer goes first (dealer is 0, so player 1 goes first)
        $this->assertEquals(1, $game->getCurrentPlayer());
    }

    public function testDealHands(): void
    {
        $game = $this->createStartedGame();
        $game->setPlayer1Hand([]);
        $game->setPlayer2Hand([]);
        $deckBefore = count($game->getDeck());

        $this->engine->dealHands($game);

        $this->assertCount(3, $game->getPlayer1Hand());
        $this->assertCount(3, $game->getPlayer2Hand());
        $this->assertCount($deckBefore - 6, $game->getDeck());
    }

    public function testFindCaptures_SingleMatch(): void
    {
        $table = [
            ['suit' => 'Denari', 'value' => 3],
            ['suit' => 'Coppe', 'value' => 7],
            ['suit' => 'Bastoni', 'value' => 5],
        ];
        $card = ['suit' => 'Spade', 'value' => 7];

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertCount(1, $captures);
        $this->assertEquals([1], $captures[0]);
    }

    public function testFindCaptures_MultipleSingleMatches(): void
    {
        $table = [
            ['suit' => 'Denari', 'value' => 5],
            ['suit' => 'Coppe', 'value' => 7],
            ['suit' => 'Bastoni', 'value' => 5],
        ];
        $card = ['suit' => 'Spade', 'value' => 5];

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertCount(2, $captures);
        $this->assertEquals([0], $captures[0]);
        $this->assertEquals([2], $captures[1]);
    }

    public function testFindCaptures_SingleMatchPriority(): void
    {
        // Single match must take priority over sum
        $table = [
            ['suit' => 'Denari', 'value' => 7],
            ['suit' => 'Coppe', 'value' => 3],
            ['suit' => 'Bastoni', 'value' => 4],
        ];
        $card = ['suit' => 'Spade', 'value' => 7];

        $captures = $this->engine->findCaptures($table, $card);

        // Only the single-card match, NOT the 3+4=7 sum
        $this->assertCount(1, $captures);
        $this->assertEquals([0], $captures[0]);
    }

    public function testFindCaptures_SumMatch(): void
    {
        $table = [
            ['suit' => 'Denari', 'value' => 3],
            ['suit' => 'Coppe', 'value' => 4],
            ['suit' => 'Bastoni', 'value' => 2],
        ];
        $card = ['suit' => 'Spade', 'value' => 7];

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertCount(1, $captures);
        $this->assertContains(0, $captures[0]); // 3
        $this->assertContains(1, $captures[0]); // 4
    }

    public function testFindCaptures_MultipleSumMatches(): void
    {
        $table = [
            ['suit' => 'Denari', 'value' => 3],
            ['suit' => 'Coppe', 'value' => 7],
            ['suit' => 'Bastoni', 'value' => 4],
            ['suit' => 'Spade', 'value' => 6],
        ];
        $card = ['suit' => 'Denari', 'value' => 10];

        $captures = $this->engine->findCaptures($table, $card);

        // 3+7=10, 4+6=10
        $this->assertCount(2, $captures);
    }

    public function testFindCaptures_NoMatch(): void
    {
        $table = [
            ['suit' => 'Denari', 'value' => 2],
            ['suit' => 'Coppe', 'value' => 3],
        ];
        $card = ['suit' => 'Spade', 'value' => 8];

        $captures = $this->engine->findCaptures($table, $card);

        $this->assertEmpty($captures);
    }

    public function testPlayCard_Place(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand([
            ['suit' => 'Denari', 'value' => 8],
        ]);
        $game->setPlayer2Hand([
            ['suit' => 'Coppe', 'value' => 1],
        ]);
        $game->setTableCards([
            ['suit' => 'Bastoni', 'value' => 2],
        ]);
        $game->setDeck(array_fill(0, 30, ['suit' => 'Denari', 'value' => 1]));

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals('place', $result['type']);
        $this->assertCount(2, $game->getTableCards());
        $this->assertEmpty($game->getPlayer1Hand());
    }

    public function testPlayCard_AutoCapture(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand([
            ['suit' => 'Denari', 'value' => 5],
        ]);
        $game->setPlayer2Hand([
            ['suit' => 'Coppe', 'value' => 1],
        ]);
        $game->setTableCards([
            ['suit' => 'Bastoni', 'value' => 5],
            ['suit' => 'Spade', 'value' => 3],
        ]);
        $game->setDeck(array_fill(0, 30, ['suit' => 'Denari', 'value' => 1]));

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals('capture', $result['type']);
        $this->assertCount(1, $game->getTableCards()); // 3 remains
        $this->assertCount(2, $game->getPlayer1Captured()); // played card + captured card
    }

    public function testPlayCard_Choosing(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand([
            ['suit' => 'Denari', 'value' => 5],
        ]);
        $game->setPlayer2Hand([
            ['suit' => 'Coppe', 'value' => 1],
        ]);
        $game->setTableCards([
            ['suit' => 'Bastoni', 'value' => 5],
            ['suit' => 'Spade', 'value' => 5],
        ]);
        $game->setDeck(array_fill(0, 30, ['suit' => 'Denari', 'value' => 1]));

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals('choosing', $result['type']);
        $this->assertEquals(GameState::Choosing, $game->getState());
        $this->assertNotNull($game->getPendingPlay());
    }

    public function testPlayCard_Scopa(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand([
            ['suit' => 'Denari', 'value' => 5],
        ]);
        $game->setPlayer2Hand([
            ['suit' => 'Coppe', 'value' => 1],
        ]);
        $game->setTableCards([
            ['suit' => 'Bastoni', 'value' => 5],
        ]);
        $game->setDeck(array_fill(0, 30, ['suit' => 'Denari', 'value' => 1]));

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals('capture', $result['type']);
        $this->assertTrue($result['scopa']);
        $this->assertEquals(1, $game->getPlayer1Scope());
    }

    public function testPlayCard_LastPlayNoScopa(): void
    {
        // Last play of round — clearing table should NOT count as scopa
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand([
            ['suit' => 'Denari', 'value' => 5],
        ]);
        $game->setPlayer2Hand([]); // opponent already played
        $game->setTableCards([
            ['suit' => 'Bastoni', 'value' => 5],
        ]);
        $game->setDeck([]); // no cards left
        $game->setPlayer1Captured([]);
        $game->setPlayer2Captured([['suit' => 'Coppe', 'value' => 1]]);

        $result = $this->engine->playCard($game, 0, 0);

        $this->assertEquals('capture', $result['type']);
        $this->assertFalse($result['scopa']);
        $this->assertEquals(0, $game->getPlayer1Scope());
    }

    public function testSelectCapture(): void
    {
        $game = new Game();
        $game->setState(GameState::Choosing);
        $game->setCurrentPlayer(0);
        $game->setTableCards([
            ['suit' => 'Bastoni', 'value' => 5],
            ['suit' => 'Spade', 'value' => 5],
            ['suit' => 'Coppe', 'value' => 3],
        ]);
        $game->setPlayer1Hand([]);
        $game->setPlayer2Hand([['suit' => 'Denari', 'value' => 1]]);
        $game->setDeck(array_fill(0, 30, ['suit' => 'Denari', 'value' => 1]));
        $game->setPendingPlay([
            'card' => ['suit' => 'Denari', 'value' => 5],
            'playerIndex' => 0,
            'options' => [[0], [1]], // two 5s
        ]);

        $result = $this->engine->selectCapture($game, 1); // select second 5

        $this->assertEquals('capture', $result['type']);
        $this->assertNull($game->getPendingPlay());
        // Table should have the first 5 and the 3, minus the second 5
        $this->assertCount(2, $game->getTableCards());
    }

    public function testEndRound_LastCapturerGetsRemaining(): void
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand([['suit' => 'Denari', 'value' => 8]]);
        $game->setPlayer2Hand([]);
        $game->setDeck([]);
        $game->setTableCards([
            ['suit' => 'Bastoni', 'value' => 2],
            ['suit' => 'Coppe', 'value' => 3],
        ]);
        $game->setLastCapturer(1);
        $game->setPlayer1Captured([]);
        $game->setPlayer2Captured([]);

        // Playing a non-matching card will place it, then end round
        $this->engine->playCard($game, 0, 0);

        // Player 2 (last capturer) should get remaining table cards
        $this->assertEmpty($game->getTableCards());
        // Player 2 captured: the 2, the 3, and the 8 that was placed on table
        $this->assertCount(3, $game->getPlayer2Captured());
    }

    public function testGetStateForPlayer(): void
    {
        $game = $this->createStartedGame();

        $state0 = $this->engine->getStateForPlayer($game, 0);
        $state1 = $this->engine->getStateForPlayer($game, 1);

        // Player 0 sees own hand
        $this->assertCount(3, $state0->myHand);
        $this->assertEquals(3, $state0->opponentHandCount);
        $this->assertEquals(0, $state0->myIndex);
        $this->assertEquals('Alice', $state0->myName);
        $this->assertEquals('Bob', $state0->opponentName);

        // Player 1 sees own hand
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
        $game->setPlayer1Hand([['suit' => 'Denari', 'value' => 8]]);
        $game->setPlayer2Hand([]);
        $game->setTableCards([['suit' => 'Coppe', 'value' => 2]]);
        $game->setDeck(array_fill(0, 12, ['suit' => 'Bastoni', 'value' => 1]));
        $game->setPlayer1Captured([]);
        $game->setPlayer2Captured([]);

        // Play the card (will place, then both hands empty, trigger re-deal)
        $this->engine->playCard($game, 0, 0);

        // Should have re-dealt
        $this->assertCount(3, $game->getPlayer1Hand());
        $this->assertCount(3, $game->getPlayer2Hand());
        $this->assertCount(6, $game->getDeck());
    }

    public function testNoDuplicateCardsOnTable(): void
    {
        // After placing a card on the table, no two table cards should be identical
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand([
            ['suit' => 'Denari', 'value' => 3],
        ]);
        $game->setPlayer2Hand([
            ['suit' => 'Coppe', 'value' => 1],
        ]);
        $game->setTableCards([
            ['suit' => 'Bastoni', 'value' => 5],
            ['suit' => 'Spade', 'value' => 7],
        ]);
        $game->setDeck(array_fill(0, 30, ['suit' => 'Bastoni', 'value' => 1]));
        $game->setPlayer1Captured([]);
        $game->setPlayer2Captured([]);

        $this->engine->playCard($game, 0, 0); // places 3 on table

        // Verify no duplicates
        $table = $game->getTableCards();
        $keys = array_map(fn($c) => $c['suit'] . '-' . $c['value'], $table);
        $this->assertCount(count($keys), array_unique($keys), 'Table should have no duplicate cards');
    }

    public function testNoDuplicateCardsAfterCapture(): void
    {
        // After capturing, the captured card must NOT remain on the table
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(0);
        $game->setPlayer1Hand([
            ['suit' => 'Denari', 'value' => 5],
        ]);
        $game->setPlayer2Hand([
            ['suit' => 'Coppe', 'value' => 1],
        ]);
        $game->setTableCards([
            ['suit' => 'Bastoni', 'value' => 5],
            ['suit' => 'Spade', 'value' => 7],
        ]);
        $game->setDeck(array_fill(0, 30, ['suit' => 'Bastoni', 'value' => 1]));
        $game->setPlayer1Captured([]);
        $game->setPlayer2Captured([]);

        $result = $this->engine->playCard($game, 0, 0); // captures 5

        $this->assertEquals('capture', $result['type']);
        // The captured card (Bastoni 5) must not be on the table
        foreach ($game->getTableCards() as $tc) {
            $this->assertFalse(
                $tc['suit'] === 'Bastoni' && $tc['value'] === 5,
                'Captured card must not remain on the table'
            );
        }
        // The played card (Denari 5) must not be on the table either
        foreach ($game->getTableCards() as $tc) {
            $this->assertFalse(
                $tc['suit'] === 'Denari' && $tc['value'] === 5,
                'Played card must not remain on the table after capture'
            );
        }
        // Only the 7 should remain
        $this->assertCount(1, $game->getTableCards());
    }

    public function testDeckIntegrity_AllCardsAccountedFor(): void
    {
        // After any number of plays, all 40 cards must be accounted for:
        // deck + table + player1Hand + player2Hand + player1Captured + player2Captured = 40
        $game = $this->createStartedGame();

        // Play several cards
        for ($turn = 0; $turn < 6; $turn++) {
            $playerIdx = $game->getCurrentPlayer();
            $hand = $game->getPlayerHand($playerIdx);
            if (count($hand) === 0) break;
            $result = $this->engine->playCard($game, $playerIdx, 0);
            if ($result['type'] === 'choosing') {
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
        // Play through multiple turns and verify table never has duplicate cards
        $game = $this->createStartedGame();

        for ($turn = 0; $turn < 6; $turn++) {
            $playerIdx = $game->getCurrentPlayer();
            $hand = $game->getPlayerHand($playerIdx);
            if (count($hand) === 0) break;

            $this->engine->playCard($game, $playerIdx, 0);
            if ($game->getState() === GameState::Choosing) {
                $this->engine->selectCapture($game, 0);
            }

            // Check table for duplicates after each play
            $table = $game->getTableCards();
            $keys = array_map(fn($c) => $c['suit'] . '-' . $c['value'], $table);
            $this->assertCount(
                count($keys),
                array_unique($keys),
                "Duplicate card on table after turn $turn"
            );
        }
    }
}
