<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Enum\Suit;
use App\ValueObject\AIMove;
use App\ValueObject\Card;

final readonly class BriscolaAIService implements AIService
{
    public function __construct(
        private BriscolaScoringService $scoringService,
    ) {}

    #[\Override]
    public function evaluateMove(Game $game, int $aiIndex): AIMove
    {
        $hand = $game->getPlayerHand($aiIndex);
        $tableCards = $game->getTableCards();
        $briscolaCard = $game->getBriscolaCard();
        $trumpSuit = $briscolaCard?->suit;
        $isLeading = count($tableCards) === 0;

        $bestScore = -PHP_FLOAT_MAX;
        $bestCardIndex = 0;

        foreach ($hand as $cardIndex => $card) {
            $score = $isLeading
                ? $this->scoreLeadPlay($card, $trumpSuit)
                : $this->scoreFollowPlay($card, $tableCards->get(0), $trumpSuit);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCardIndex = $cardIndex;
            }
        }

        return new AIMove(cardIndex: $bestCardIndex);
    }

    #[\Override]
    public function autoSelectCapture(Game $game): int
    {
        throw new \LogicException('Briscola does not support capture selection');
    }

    /**
     * Score a card for leading a trick.
     * Strategy: lead with low-value non-trump cards to minimize risk.
     */
    private function scoreLeadPlay(Card $card, ?Suit $trumpSuit): float
    {
        $score = 0.0;
        $points = $this->scoringService->getCardPoints($card);
        $strength = $this->scoringService->getCardStrength($card);
        $isTrump = $trumpSuit !== null && $card->suit === $trumpSuit;

        // Heavily penalize leading with trump — save them for capturing
        if ($isTrump) {
            $score -= 100;
        }

        // Penalize leading with high-point cards (they might get captured)
        $score -= $points * 5;

        // Penalize leading with strong cards (save them for following)
        $score -= $strength * 2;

        // Prefer leading with zero-point cards
        if ($points === 0) {
            $score += 30;
        }

        return $score;
    }

    /**
     * Score a card for following (responding to opponent's lead).
     * Strategy: win valuable tricks, lose cheaply on worthless ones.
     */
    private function scoreFollowPlay(Card $card, Card $leaderCard, ?Suit $trumpSuit): float
    {
        $score = 0.0;
        $leaderPoints = $this->scoringService->getCardPoints($leaderCard);
        $cardPoints = $this->scoringService->getCardPoints($card);
        $cardStrength = $this->scoringService->getCardStrength($card);
        $isTrump = $trumpSuit !== null && $card->suit === $trumpSuit;
        $leaderIsTrump = $trumpSuit !== null && $leaderCard->suit === $trumpSuit;
        $sameSuit = $card->suit === $leaderCard->suit;

        // Determine if this card would win the trick
        $wouldWin = false;
        if ($isTrump && !$leaderIsTrump) {
            $wouldWin = true;
        } elseif ($isTrump) {
            $wouldWin = $cardStrength > $this->scoringService->getCardStrength($leaderCard);
        } elseif (!$leaderIsTrump && $sameSuit) {
            $wouldWin = $cardStrength > $this->scoringService->getCardStrength($leaderCard);
        }
        // Different non-trump suits: leader wins, so wouldWin stays false

        $trickValue = $leaderPoints + $cardPoints;

        if ($wouldWin) {
            // Reward winning valuable tricks
            $score += $trickValue * 3;

            // But penalize using trump on worthless tricks
            if ($isTrump && !$leaderIsTrump && $leaderPoints === 0) {
                $score -= 50;
            }

            // Prefer winning with minimum strength needed
            $score -= $cardStrength;

            // Extra reward for capturing Aces and Threes
            if ($leaderCard->value === 1 || $leaderCard->value === 3) {
                $score += 40;
            }
        } else {
            // When losing, prefer to lose with zero-point cards
            $score -= $cardPoints * 8;

            // Prefer discarding low-strength cards
            $score -= $cardStrength;

            // Strong preference for discarding worthless off-suit cards
            if (!$sameSuit && !$isTrump && $cardPoints === 0) {
                $score += 25;
            }
        }

        return $score;
    }
}
