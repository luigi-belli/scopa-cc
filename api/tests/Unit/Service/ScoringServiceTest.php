<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Enum\Suit;
use App\Service\ScoringService;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use PHPUnit\Framework\TestCase;

class ScoringServiceTest extends TestCase
{
    private ScoringService $service;

    protected function setUp(): void
    {
        $this->service = new ScoringService();
    }

    private function createGameWithCaptures(CardCollection $p1Captured, CardCollection $p2Captured): Game
    {
        $game = new Game();
        $game->setPlayer1Captured($p1Captured);
        $game->setPlayer2Captured($p2Captured);
        $game->setPlayer1Scope(0);
        $game->setPlayer2Scope(0);
        return $game;
    }

    public function testCarte_MoreCards(): void
    {
        $game = $this->createGameWithCaptures(
            CardCollection::fill(22, new Card(Suit::Denari, 1)),
            CardCollection::fill(18, new Card(Suit::Coppe, 1))
        );

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores->player1->carte);
        $this->assertEquals(0, $scores->player2->carte);
        $this->assertSame(22, $scores->player1->carteCount);
        $this->assertSame(18, $scores->player2->carteCount);
    }

    public function testCarte_Tied(): void
    {
        $game = $this->createGameWithCaptures(
            CardCollection::fill(20, new Card(Suit::Denari, 1)),
            CardCollection::fill(20, new Card(Suit::Coppe, 1))
        );

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(0, $scores->player1->carte);
        $this->assertEquals(0, $scores->player2->carte);
        $this->assertSame(20, $scores->player1->carteCount);
        $this->assertSame(20, $scores->player2->carteCount);
    }

    public function testDenari_MoreDenari(): void
    {
        $p1Cards = [];
        for ($i = 1; $i <= 6; $i++) {
            $p1Cards[] = new Card(Suit::Denari, $i);
        }
        $p2Cards = [];
        for ($i = 7; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Denari, $i);
        }

        $game = $this->createGameWithCaptures(new CardCollection($p1Cards), new CardCollection($p2Cards));
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores->player1->denari);
        $this->assertEquals(0, $scores->player2->denari);
        $this->assertSame(6, $scores->player1->denariCount);
        $this->assertSame(4, $scores->player2->denariCount);
    }

    public function testDenari_Tied(): void
    {
        $p1Cards = [];
        for ($i = 1; $i <= 5; $i++) {
            $p1Cards[] = new Card(Suit::Denari, $i);
        }
        $p2Cards = [];
        for ($i = 6; $i <= 10; $i++) {
            $p2Cards[] = new Card(Suit::Denari, $i);
        }

        $game = $this->createGameWithCaptures(new CardCollection($p1Cards), new CardCollection($p2Cards));
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(0, $scores->player1->denari);
        $this->assertEquals(0, $scores->player2->denari);
    }

    public function testSetteBello_Player1Has(): void
    {
        $game = $this->createGameWithCaptures(
            new CardCollection([new Card(Suit::Denari, 7)]),
            new CardCollection([new Card(Suit::Coppe, 7)])
        );

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores->player1->setteBello);
        $this->assertEquals(0, $scores->player2->setteBello);
        $this->assertTrue($scores->player1->hasSetteBello);
        $this->assertFalse($scores->player2->hasSetteBello);
    }

    public function testSetteBello_Player2Has(): void
    {
        $game = $this->createGameWithCaptures(
            new CardCollection([new Card(Suit::Coppe, 7)]),
            new CardCollection([new Card(Suit::Denari, 7)])
        );

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(0, $scores->player1->setteBello);
        $this->assertEquals(1, $scores->player2->setteBello);
        $this->assertFalse($scores->player1->hasSetteBello);
        $this->assertTrue($scores->player2->hasSetteBello);
    }

    public function testPrimiera_Normal(): void
    {
        $p1 = new CardCollection([
            new Card(Suit::Denari, 7),
            new Card(Suit::Coppe, 7),
            new Card(Suit::Bastoni, 7),
            new Card(Suit::Spade, 7),
        ]);
        $p2 = new CardCollection([
            new Card(Suit::Denari, 6),
            new Card(Suit::Coppe, 6),
            new Card(Suit::Bastoni, 6),
            new Card(Suit::Spade, 6),
        ]);

        $game = $this->createGameWithCaptures($p1, $p2);
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores->player1->primiera);
        $this->assertEquals(0, $scores->player2->primiera);
        $this->assertSame(84, $scores->player1->primieraValue);
        $this->assertSame(72, $scores->player2->primieraValue);
    }

    public function testPrimiera_OneMissingSuit(): void
    {
        $p1 = new CardCollection([
            new Card(Suit::Denari, 1),
            new Card(Suit::Coppe, 1),
            new Card(Suit::Bastoni, 1),
            new Card(Suit::Spade, 1),
        ]);
        $p2 = new CardCollection([
            new Card(Suit::Denari, 7),
            new Card(Suit::Coppe, 7),
            new Card(Suit::Bastoni, 7),
        ]);

        $game = $this->createGameWithCaptures($p1, $p2);
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores->player1->primiera);
        $this->assertEquals(0, $scores->player2->primiera);
        $this->assertSame(64, $scores->player1->primieraValue);
        $this->assertNull($scores->player2->primieraValue);
    }

    public function testPrimiera_BothMissingSuit(): void
    {
        $p1 = new CardCollection([
            new Card(Suit::Denari, 7),
            new Card(Suit::Coppe, 7),
        ]);
        $p2 = new CardCollection([
            new Card(Suit::Bastoni, 7),
            new Card(Suit::Spade, 7),
        ]);

        $game = $this->createGameWithCaptures($p1, $p2);
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(0, $scores->player1->primiera);
        $this->assertEquals(0, $scores->player2->primiera);
        $this->assertNull($scores->player1->primieraValue);
        $this->assertNull($scores->player2->primieraValue);
    }

    public function testPrimiera_ValueMapping(): void
    {
        $expected = [7 => 21, 6 => 18, 1 => 16, 5 => 15, 4 => 14, 3 => 13, 2 => 12, 8 => 10, 9 => 10, 10 => 10];

        foreach ($expected as $cardValue => $primieraValue) {
            $this->assertEquals($primieraValue, $this->service->getPrimieraValue($cardValue));
        }
    }

    public function testScope_Count(): void
    {
        $game = $this->createGameWithCaptures(new CardCollection(), new CardCollection());
        $game->setPlayer1Scope(3);
        $game->setPlayer2Scope(1);

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(3, $scores->player1->scope);
        $this->assertEquals(1, $scores->player2->scope);
    }

    public function testFullRoundScoring(): void
    {
        $p1 = new CardCollection([
            new Card(Suit::Denari, 7),
            new Card(Suit::Denari, 1),
            new Card(Suit::Coppe, 6),
            new Card(Suit::Bastoni, 5),
            new Card(Suit::Spade, 4),
        ]);
        $p2 = new CardCollection([
            new Card(Suit::Denari, 2),
            new Card(Suit::Coppe, 7),
            new Card(Suit::Bastoni, 7),
            new Card(Suit::Spade, 7),
        ]);

        $game = $this->createGameWithCaptures($p1, $p2);
        $game->setPlayer1Scope(1);
        $game->setPlayer2Scope(0);

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores->player1->carte);
        $this->assertEquals(1, $scores->player1->denari);
        $this->assertEquals(1, $scores->player1->setteBello);
        $this->assertEquals(0, $scores->player1->primiera);
        $this->assertEquals(1, $scores->player2->primiera);
        $this->assertEquals(1, $scores->player1->scope);
        $this->assertEquals(0, $scores->player2->scope);

        $this->assertSame(5, $scores->player1->carteCount);
        $this->assertSame(4, $scores->player2->carteCount);
        $this->assertSame(2, $scores->player1->denariCount);
        $this->assertSame(1, $scores->player2->denariCount);
        $this->assertTrue($scores->player1->hasSetteBello);
        $this->assertFalse($scores->player2->hasSetteBello);
        $this->assertSame(68, $scores->player1->primieraValue);
        $this->assertSame(75, $scores->player2->primieraValue);

        $this->assertEquals(4, $scores->player1->total());
        $this->assertEquals(1, $scores->player2->total());
    }
}
