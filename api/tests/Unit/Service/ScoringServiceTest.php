<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Service\ScoringService;
use PHPUnit\Framework\TestCase;

class ScoringServiceTest extends TestCase
{
    private ScoringService $service;

    protected function setUp(): void
    {
        $this->service = new ScoringService();
    }

    private function createGameWithCaptures(array $p1Captured, array $p2Captured): Game
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
            array_fill(0, 22, ['suit' => 'Denari', 'value' => 1]),
            array_fill(0, 18, ['suit' => 'Coppe', 'value' => 1])
        );

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores[0]['carte']);
        $this->assertEquals(0, $scores[1]['carte']);
    }

    public function testCarte_Tied(): void
    {
        $game = $this->createGameWithCaptures(
            array_fill(0, 20, ['suit' => 'Denari', 'value' => 1]),
            array_fill(0, 20, ['suit' => 'Coppe', 'value' => 1])
        );

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(0, $scores[0]['carte']);
        $this->assertEquals(0, $scores[1]['carte']);
    }

    public function testDenari_MoreDenari(): void
    {
        $p1 = [];
        for ($i = 1; $i <= 6; $i++) {
            $p1[] = ['suit' => 'Denari', 'value' => $i];
        }
        $p2 = [];
        for ($i = 7; $i <= 10; $i++) {
            $p2[] = ['suit' => 'Denari', 'value' => $i];
        }

        $game = $this->createGameWithCaptures($p1, $p2);
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores[0]['denari']);
        $this->assertEquals(0, $scores[1]['denari']);
    }

    public function testDenari_Tied(): void
    {
        $p1 = [];
        for ($i = 1; $i <= 5; $i++) {
            $p1[] = ['suit' => 'Denari', 'value' => $i];
        }
        $p2 = [];
        for ($i = 6; $i <= 10; $i++) {
            $p2[] = ['suit' => 'Denari', 'value' => $i];
        }

        $game = $this->createGameWithCaptures($p1, $p2);
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(0, $scores[0]['denari']);
        $this->assertEquals(0, $scores[1]['denari']);
    }

    public function testSetteBello_Player1Has(): void
    {
        $game = $this->createGameWithCaptures(
            [['suit' => 'Denari', 'value' => 7]],
            [['suit' => 'Coppe', 'value' => 7]]
        );

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores[0]['setteBello']);
        $this->assertEquals(0, $scores[1]['setteBello']);
    }

    public function testSetteBello_Player2Has(): void
    {
        $game = $this->createGameWithCaptures(
            [['suit' => 'Coppe', 'value' => 7]],
            [['suit' => 'Denari', 'value' => 7]]
        );

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(0, $scores[0]['setteBello']);
        $this->assertEquals(1, $scores[1]['setteBello']);
    }

    public function testPrimiera_Normal(): void
    {
        // Player 1: 7d(21) + 7c(21) + 7b(21) + 7s(21) = 84
        $p1 = [
            ['suit' => 'Denari', 'value' => 7],
            ['suit' => 'Coppe', 'value' => 7],
            ['suit' => 'Bastoni', 'value' => 7],
            ['suit' => 'Spade', 'value' => 7],
        ];
        // Player 2: 6d(18) + 6c(18) + 6b(18) + 6s(18) = 72
        $p2 = [
            ['suit' => 'Denari', 'value' => 6],
            ['suit' => 'Coppe', 'value' => 6],
            ['suit' => 'Bastoni', 'value' => 6],
            ['suit' => 'Spade', 'value' => 6],
        ];

        $game = $this->createGameWithCaptures($p1, $p2);
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores[0]['primiera']);
        $this->assertEquals(0, $scores[1]['primiera']);
    }

    public function testPrimiera_OneMissingSuit(): void
    {
        // Player 1 has all 4 suits
        $p1 = [
            ['suit' => 'Denari', 'value' => 1],
            ['suit' => 'Coppe', 'value' => 1],
            ['suit' => 'Bastoni', 'value' => 1],
            ['suit' => 'Spade', 'value' => 1],
        ];
        // Player 2 missing Spade
        $p2 = [
            ['suit' => 'Denari', 'value' => 7],
            ['suit' => 'Coppe', 'value' => 7],
            ['suit' => 'Bastoni', 'value' => 7],
        ];

        $game = $this->createGameWithCaptures($p1, $p2);
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(1, $scores[0]['primiera']);
        $this->assertEquals(0, $scores[1]['primiera']);
    }

    public function testPrimiera_BothMissingSuit(): void
    {
        // Both missing suits
        $p1 = [
            ['suit' => 'Denari', 'value' => 7],
            ['suit' => 'Coppe', 'value' => 7],
        ];
        $p2 = [
            ['suit' => 'Bastoni', 'value' => 7],
            ['suit' => 'Spade', 'value' => 7],
        ];

        $game = $this->createGameWithCaptures($p1, $p2);
        $scores = $this->service->scoreRound($game);

        $this->assertEquals(0, $scores[0]['primiera']);
        $this->assertEquals(0, $scores[1]['primiera']);
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
        $game = $this->createGameWithCaptures([], []);
        $game->setPlayer1Scope(3);
        $game->setPlayer2Scope(1);

        $scores = $this->service->scoreRound($game);

        $this->assertEquals(3, $scores[0]['scope']);
        $this->assertEquals(1, $scores[1]['scope']);
    }

    public function testFullRoundScoring(): void
    {
        // Comprehensive scenario
        $p1 = [
            ['suit' => 'Denari', 'value' => 7], // sette bello + denari + 7(21 primiera)
            ['suit' => 'Denari', 'value' => 1], // denari + 1(16 primiera for Denari, but 7 is better)
            ['suit' => 'Coppe', 'value' => 6],  // 18 primiera
            ['suit' => 'Bastoni', 'value' => 5], // 15 primiera
            ['suit' => 'Spade', 'value' => 4],  // 14 primiera
            // p1 primiera: 21 + 18 + 15 + 14 = 68
        ];
        $p2 = [
            ['suit' => 'Denari', 'value' => 2],
            ['suit' => 'Coppe', 'value' => 7],  // 21 primiera
            ['suit' => 'Bastoni', 'value' => 7], // 21 primiera
            ['suit' => 'Spade', 'value' => 7],  // 21 primiera
            // p2 primiera: 12 + 21 + 21 + 21 = 75
        ];

        $game = $this->createGameWithCaptures($p1, $p2);
        $game->setPlayer1Scope(1);
        $game->setPlayer2Scope(0);

        $scores = $this->service->scoreRound($game);

        // Carte: p1=5, p2=4 → p1 wins
        $this->assertEquals(1, $scores[0]['carte']);
        // Denari: p1=2, p2=1 → p1 wins
        $this->assertEquals(1, $scores[0]['denari']);
        // Sette bello: p1 has 7d
        $this->assertEquals(1, $scores[0]['setteBello']);
        // Primiera: p2 has 75 > p1's 68
        $this->assertEquals(0, $scores[0]['primiera']);
        $this->assertEquals(1, $scores[1]['primiera']);
        // Scope: p1=1
        $this->assertEquals(1, $scores[0]['scope']);
        $this->assertEquals(0, $scores[1]['scope']);

        // Totals: p1=4, p2=1
        $this->assertEquals(4, $this->service->totalRoundScore($scores[0]));
        $this->assertEquals(1, $this->service->totalRoundScore($scores[1]));
    }
}
