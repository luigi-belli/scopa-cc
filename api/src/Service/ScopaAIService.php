<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Enum\Suit;
use App\ValueObject\AIMove;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;

final readonly class ScopaAIService implements AIService
{
    public function __construct(
        private ScopaEngine $engine,
        private ScopaScoringService $scoringService,
    ) {}

    #[\Override]
    public function evaluateMove(Game $game, int $aiIndex): AIMove
    {
        $hand = $game->getPlayerHand($aiIndex);
        $tableCards = $game->getTableCards();
        $bestScore = -PHP_FLOAT_MAX;
        $bestCardIndex = 0;
        $bestOptionIndex = null;

        foreach ($hand as $cardIndex => $card) {
            $captures = $this->engine->findCaptures($tableCards, $card);

            if (count($captures) === 0) {
                $score = $this->scorePlacement($card, $tableCards);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCardIndex = $cardIndex;
                    $bestOptionIndex = null;
                }
            } else {
                foreach ($captures as $optIdx => $captureIndices) {
                    $capturedCards = [];
                    foreach ($captureIndices as $idx) {
                        $capturedCards[] = $tableCards->get($idx);
                    }
                    $score = $this->scoreCapture($card, new CardCollection($capturedCards), $tableCards, count($captureIndices) === count($tableCards));
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestCardIndex = $cardIndex;
                        $bestOptionIndex = count($captures) > 1 ? $optIdx : null;
                    }
                }
            }
        }

        return new AIMove(cardIndex: $bestCardIndex, optionIndex: $bestOptionIndex);
    }

    #[\Override]
    public function autoSelectCapture(Game $game): int
    {
        $pending = $game->getPendingPlay();
        if ($pending === null) {
            return 0;
        }

        $tableCards = $game->getTableCards();
        $playedCard = $pending->card;
        $options = $pending->options;
        $bestScore = -PHP_FLOAT_MAX;
        $bestIdx = 0;

        foreach ($options as $optIdx => $captureIndices) {
            $capturedCards = [];
            foreach ($captureIndices as $idx) {
                $capturedCards[] = $tableCards->get($idx);
            }
            $score = $this->scoreCapture($playedCard, new CardCollection($capturedCards), $tableCards, count($captureIndices) === count($tableCards));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx = $optIdx;
            }
        }

        return $bestIdx;
    }

    private function scoreCapture(Card $playedCard, CardCollection $capturedCards, CardCollection $tableCards, bool $clearsTable): float
    {
        $score = 0.0;
        $allCaptured = (new CardCollection([$playedCard]))->withAppended(...$capturedCards->toArray());

        foreach ($allCaptured as $card) {
            $score += 10;
            if ($card->suit === Suit::Denari) {
                $score += 15;
            }
            if ($card->isSetteBello()) {
                $score += 100;
            }
            $score += $this->scoringService->getPrimieraValue($card->value) * 0.5;
            if ($card->value === 7) {
                $score += 12;
            }
            if ($card->value === 6) {
                $score += 6;
            }
        }

        if ($clearsTable) {
            $score += 80;
        }

        if (count($capturedCards) > 1) {
            $score += 3;
        }

        return $score;
    }

    private function scorePlacement(Card $card, CardCollection $tableCards): float
    {
        $score = 0.0;

        $score -= $this->scoringService->getPrimieraValue($card->value) * 0.8;

        if ($card->isSetteBello()) {
            $score -= 120;
        }

        if ($card->suit === Suit::Denari) {
            $score -= 20;
        }

        if ($card->value === 7) {
            $score -= 25;
        }

        $tableSum = 0;
        foreach ($tableCards as $tc) {
            $tableSum += $tc->value;
        }
        $newSum = $tableSum + $card->value;
        if ($newSum <= 10) {
            $score -= 30;
        }

        foreach ($tableCards as $tc) {
            if ($tc->value === $card->value) {
                $score -= 15;
                break;
            }
        }

        if ($card->value >= 8) {
            $score += 5;
        }

        return $score;
    }
}
