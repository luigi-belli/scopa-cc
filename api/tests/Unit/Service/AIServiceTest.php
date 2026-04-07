<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Enum\GameState;
use App\Service\AIService;
use App\Service\DeckService;
use App\Service\GameEngine;
use App\Service\ScoringService;
use PHPUnit\Framework\TestCase;

class AIServiceTest extends TestCase
{
    private AIService $ai;
    private GameEngine $engine;

    protected function setUp(): void
    {
        $scoringService = new ScoringService();
        $this->engine = new GameEngine(new DeckService(), $scoringService);
        $this->ai = new AIService($this->engine, $scoringService);
    }

    /** @param list<array{suit: string, value: int}> $aiHand
     *  @param list<array{suit: string, value: int}> $table */
    private function createGame(array $aiHand, array $table): Game
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(1); // AI is player 2
        $game->setPlayer1Hand([]);
        $game->setPlayer2Hand($aiHand);
        $game->setTableCards($table);
        $game->setDeck(array_fill(0, 20, ['suit' => 'Bastoni', 'value' => 1]));
        $game->setPlayer1Captured([]);
        $game->setPlayer2Captured([]);
        return $game;
    }

    public function testEvaluateMove_PrefersCapture(): void
    {
        $game = $this->createGame(
            [
                ['suit' => 'Denari', 'value' => 3], // can capture
                ['suit' => 'Coppe', 'value' => 8],  // must place
            ],
            [['suit' => 'Bastoni', 'value' => 3]]
        );

        $move = $this->ai->evaluateMove($game, 1);

        $this->assertEquals(0, $move['cardIndex']); // Should prefer the capturing card
    }

    public function testEvaluateMove_PrefersSetteBello(): void
    {
        $game = $this->createGame(
            [
                ['suit' => 'Coppe', 'value' => 5],  // can capture a 5
                ['suit' => 'Denari', 'value' => 7],  // can capture 7d (sette bello on table via sum)
            ],
            [
                ['suit' => 'Bastoni', 'value' => 5],
                ['suit' => 'Denari', 'value' => 7],  // sette bello!
            ]
        );

        $move = $this->ai->evaluateMove($game, 1);

        // Should prefer capturing 7 of Denari
        $this->assertEquals(1, $move['cardIndex']);
    }

    public function testEvaluateMove_PrefersScopa(): void
    {
        $game = $this->createGame(
            [
                ['suit' => 'Coppe', 'value' => 5],  // captures one of two cards
                ['suit' => 'Spade', 'value' => 8],  // captures both (3+5=8), scopa!
            ],
            [
                ['suit' => 'Bastoni', 'value' => 5],
                ['suit' => 'Denari', 'value' => 3],
            ]
        );

        $move = $this->ai->evaluateMove($game, 1);

        $this->assertEquals(1, $move['cardIndex']); // Scopa is worth +80
    }

    public function testEvaluateMove_PrefersMoreCards(): void
    {
        $game = $this->createGame(
            [
                ['suit' => 'Denari', 'value' => 10], // captures 4+6 (3 cards total)
            ],
            [
                ['suit' => 'Bastoni', 'value' => 4],
                ['suit' => 'Coppe', 'value' => 6],
                ['suit' => 'Spade', 'value' => 2],
            ]
        );

        $move = $this->ai->evaluateMove($game, 1);

        $this->assertEquals(0, $move['cardIndex']);
    }

    public function testAutoSelectCapture(): void
    {
        $game = new Game();
        $game->setState(GameState::Choosing);
        $game->setTableCards([
            ['suit' => 'Denari', 'value' => 5], // Denari worth more
            ['suit' => 'Coppe', 'value' => 5],
        ]);
        $game->setPendingPlay([
            'card' => ['suit' => 'Spade', 'value' => 5],
            'playerIndex' => 1,
            'options' => [[0], [1]],
        ]);

        $optionIndex = $this->ai->autoSelectCapture($game);

        // Should prefer the Denari card (higher score due to denari bonus)
        $this->assertEquals(0, $optionIndex);
    }
}
