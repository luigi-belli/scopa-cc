<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Enum\GameState;
use App\Enum\Suit;
use App\Service\DeckService;
use App\Service\ScopaAIService;
use App\Service\ScopaEngine;
use App\Service\ScopaScoringService;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;
use App\ValueObject\PendingPlay;
use PHPUnit\Framework\TestCase;

class AIServiceTest extends TestCase
{
    private ScopaAIService $ai;
    private ScopaEngine $engine;

    protected function setUp(): void
    {
        $scoringService = new ScopaScoringService();
        $this->engine = new ScopaEngine(new DeckService(), $scoringService);
        $this->ai = new ScopaAIService($this->engine, $scoringService);
    }

    private function createGame(CardCollection $aiHand, CardCollection $table): Game
    {
        $game = new Game();
        $game->setState(GameState::Playing);
        $game->setCurrentPlayer(1);
        $game->setPlayer1Hand(new CardCollection());
        $game->setPlayer2Hand($aiHand);
        $game->setTableCards($table);
        $game->setDeck(CardCollection::fill(20, new Card(Suit::Bastoni, 1)));
        $game->setPlayer1Captured(new CardCollection());
        $game->setPlayer2Captured(new CardCollection());
        return $game;
    }

    public function testEvaluateMove_PrefersCapture(): void
    {
        $game = $this->createGame(
            new CardCollection([
                new Card(Suit::Denari, 3),
                new Card(Suit::Coppe, 8),
            ]),
            new CardCollection([new Card(Suit::Bastoni, 3)])
        );

        $move = $this->ai->evaluateMove($game, 1);

        $this->assertEquals(0, $move->cardIndex);
    }

    public function testEvaluateMove_PrefersSetteBello(): void
    {
        $game = $this->createGame(
            new CardCollection([
                new Card(Suit::Coppe, 5),
                new Card(Suit::Denari, 7),
            ]),
            new CardCollection([
                new Card(Suit::Bastoni, 5),
                new Card(Suit::Denari, 7),
            ])
        );

        $move = $this->ai->evaluateMove($game, 1);

        $this->assertEquals(1, $move->cardIndex);
    }

    public function testEvaluateMove_PrefersScopa(): void
    {
        $game = $this->createGame(
            new CardCollection([
                new Card(Suit::Coppe, 5),
                new Card(Suit::Spade, 8),
            ]),
            new CardCollection([
                new Card(Suit::Bastoni, 5),
                new Card(Suit::Denari, 3),
            ])
        );

        $move = $this->ai->evaluateMove($game, 1);

        $this->assertEquals(1, $move->cardIndex);
    }

    public function testEvaluateMove_PrefersMoreCards(): void
    {
        $game = $this->createGame(
            new CardCollection([new Card(Suit::Denari, 10)]),
            new CardCollection([
                new Card(Suit::Bastoni, 4),
                new Card(Suit::Coppe, 6),
                new Card(Suit::Spade, 2),
            ])
        );

        $move = $this->ai->evaluateMove($game, 1);

        $this->assertEquals(0, $move->cardIndex);
    }

    public function testAutoSelectCapture(): void
    {
        $game = new Game();
        $game->setState(GameState::Choosing);
        $game->setTableCards(new CardCollection([
            new Card(Suit::Denari, 5),
            new Card(Suit::Coppe, 5),
        ]));
        $game->setPendingPlay(new PendingPlay(
            card: new Card(Suit::Spade, 5),
            playerIndex: 1,
            options: [[0], [1]],
        ));

        $optionIndex = $this->ai->autoSelectCapture($game);

        $this->assertEquals(0, $optionIndex);
    }
}
