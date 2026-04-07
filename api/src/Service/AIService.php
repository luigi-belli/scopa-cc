<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;

/**
 * @phpstan-import-type Card from Game
 */
final class AIService
{
    public function __construct(
        private readonly GameEngine $gameEngine,
        private readonly ScoringService $scoringService,
    ) {}

    /**
     * @return array{cardIndex: int, optionIndex: int|null}
     */
    public function evaluateMove(Game $game, int $aiIndex): array
    {
        $hand = $game->getPlayerHand($aiIndex);
        $tableCards = $game->getTableCards();
        $bestScore = -PHP_FLOAT_MAX;
        $bestCardIndex = 0;
        $bestOptionIndex = null;

        foreach ($hand as $cardIndex => $card) {
            $captures = $this->gameEngine->findCaptures($tableCards, $card);

            if (count($captures) === 0) {
                // Placement
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
                        $capturedCards[] = $tableCards[$idx];
                    }
                    $score = $this->scoreCapture($card, $capturedCards, $tableCards, count($captureIndices) === count($tableCards));
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestCardIndex = $cardIndex;
                        $bestOptionIndex = count($captures) > 1 ? $optIdx : null;
                    }
                }
            }
        }

        return [
            'cardIndex' => $bestCardIndex,
            'optionIndex' => $bestOptionIndex,
        ];
    }

    public function autoSelectCapture(Game $game): int
    {
        $pending = $game->getPendingPlay();
        if ($pending === null) {
            return 0;
        }

        $tableCards = $game->getTableCards();
        $playedCard = $pending['card'];
        $options = $pending['options'];
        $bestScore = -PHP_FLOAT_MAX;
        $bestIdx = 0;

        foreach ($options as $optIdx => $captureIndices) {
            $capturedCards = [];
            foreach ($captureIndices as $idx) {
                $capturedCards[] = $tableCards[$idx];
            }
            $score = $this->scoreCapture($playedCard, $capturedCards, $tableCards, count($captureIndices) === count($tableCards));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx = $optIdx;
            }
        }

        return $bestIdx;
    }

    /**
     * @param Card $playedCard
     * @param list<Card> $capturedCards
     * @param list<Card> $tableCards
     */
    private function scoreCapture(array $playedCard, array $capturedCards, array $tableCards, bool $clearsTable): float
    {
        $score = 0.0;
        $allCaptured = array_merge([$playedCard], $capturedCards);

        foreach ($allCaptured as $card) {
            $score += 10; // +10 per card
            if ($card['suit'] === 'Denari') {
                $score += 15; // +15 per denari
            }
            if ($card['suit'] === 'Denari' && $card['value'] === 7) {
                $score += 100; // +100 sette bello
            }
            $score += $this->scoringService->getPrimieraValue($card['value']) * 0.5; // +0.5x primiera
            if ($card['value'] === 7) {
                $score += 12; // +12 per seven
            }
            if ($card['value'] === 6) {
                $score += 6; // +6 per six
            }
        }

        if ($clearsTable) {
            $score += 80; // +80 scopa
        }

        if (count($capturedCards) > 1) {
            $score += 3; // +3 multi-card bonus
        }

        return $score;
    }

    /**
     * @param Card $card
     * @param list<Card> $tableCards
     */
    private function scorePlacement(array $card, array $tableCards): float
    {
        $score = 0.0;

        $score -= $this->scoringService->getPrimieraValue($card['value']) * 0.8; // -0.8x primiera value lost

        if ($card['suit'] === 'Denari' && $card['value'] === 7) {
            $score -= 120; // -120 sette bello at risk
        }

        if ($card['suit'] === 'Denari') {
            $score -= 20; // -20 denari at risk
        }

        if ($card['value'] === 7) {
            $score -= 25; // -25 sevens at risk
        }

        // Check if placing creates easy scopa (total table value easily summed)
        $tableSum = 0;
        foreach ($tableCards as $tc) {
            $tableSum += $tc['value'];
        }
        $newSum = $tableSum + $card['value'];
        if ($newSum <= 10) {
            $score -= 30; // -30 easy scopa risk
        }

        // Check if card matches value on table (opponent can capture)
        foreach ($tableCards as $tc) {
            if ($tc['value'] === $card['value']) {
                $score -= 15; // -15 matching value on table
                break;
            }
        }

        // Face cards are less valuable to opponents
        if ($card['value'] >= 8) {
            $score += 5; // +5 face cards
        }

        return $score;
    }
}
