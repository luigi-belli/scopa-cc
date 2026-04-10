<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Enum\Suit;
use App\ValueObject\AIMove;
use App\ValueObject\Card;
use App\ValueObject\CardCollection;

final readonly class TressetteAIService implements AIService
{
    public function __construct(
        private TressetteScoringService $scoringService,
    ) {}

    public function evaluateMove(Game $game, int $aiIndex): AIMove
    {
        $hand = $game->getPlayerHand($aiIndex);
        $tableCards = $game->getTableCards();
        $isLeading = count($tableCards) === 0;

        $bestScore = -PHP_FLOAT_MAX;
        $bestCardIndex = 0;

        foreach ($hand as $cardIndex => $card) {
            // Must follow suit when not leading (if we have a matching card)
            if (!$isLeading) {
                $leaderCard = $tableCards->get(0);
                if ($card->suit !== $leaderCard->suit && $this->handHasSuit($hand, $leaderCard->suit)) {
                    continue;
                }
            }

            $score = $isLeading
                ? $this->scoreLeadPlay($card, $hand)
                : $this->scoreFollowPlay($card, $tableCards->get(0));

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCardIndex = $cardIndex;
            }
        }

        return new AIMove(cardIndex: $bestCardIndex);
    }

    public function autoSelectCapture(Game $game): int
    {
        throw new \LogicException('Tressette does not support capture selection');
    }

    /**
     * Score a card for leading a trick.
     * Strategy: lead with low-value cards to minimize risk.
     * Prefer suits where we have depth (suit control, since opponent must follow).
     */
    private function scoreLeadPlay(Card $card, CardCollection $hand): float
    {
        $score = 0.0;
        $points = $this->scoringService->getCardPoints($card);
        $strength = $this->scoringService->getCardStrength($card);

        // Penalize leading with high-point cards (might get captured)
        $score -= $points * 5;

        // Penalize leading with strong cards (save them for following)
        $score -= $strength * 2;

        // Prefer leading with zero-point cards
        if ($points === 0) {
            $score += 30;
        }

        // Prefer leading suits where we have depth (suit control)
        $suitCount = $this->countSuit($hand, $card->suit);
        $score += $suitCount * 3;

        // Leading with strongest card in a long suit is good for suit control
        if ($suitCount >= 3 && $strength >= 8) {
            $score += 15;
        }

        return $score;
    }

    /**
     * Score a card for following (responding to opponent's lead).
     * Strategy: win valuable tricks, lose cheaply on worthless ones.
     */
    private function scoreFollowPlay(Card $card, Card $leaderCard): float
    {
        $score = 0.0;
        $leaderPoints = $this->scoringService->getCardPoints($leaderCard);
        $cardPoints = $this->scoringService->getCardPoints($card);
        $cardStrength = $this->scoringService->getCardStrength($card);
        $sameSuit = $card->suit === $leaderCard->suit;

        // Determine if this card would win the trick
        $wouldWin = $sameSuit
            && $cardStrength > $this->scoringService->getCardStrength($leaderCard);

        $trickValue = $leaderPoints + $cardPoints;

        if ($wouldWin) {
            // Reward winning valuable tricks
            $score += $trickValue * 3;

            // Prefer winning with minimum strength needed
            $score -= $cardStrength;

            // Extra reward for capturing Aces and 3s (high-value cards)
            if ($leaderCard->value === 1) {
                $score += 40;
            }
            if ($leaderCard->value === 3 || $leaderCard->value === 2) {
                $score += 20;
            }
        } else {
            // When losing, prefer to lose with zero-point cards
            $score -= $cardPoints * 8;

            // Prefer discarding low-strength cards
            $score -= $cardStrength;

            // Strong preference for discarding worthless off-suit cards
            if (!$sameSuit && $cardPoints === 0) {
                $score += 25;
            }
        }

        return $score;
    }

    private function handHasSuit(CardCollection $hand, Suit $suit): bool
    {
        foreach ($hand as $card) {
            if ($card->suit === $suit) {
                return true;
            }
        }
        return false;
    }

    private function countSuit(CardCollection $hand, Suit $suit): int
    {
        $count = 0;
        foreach ($hand as $card) {
            if ($card->suit === $suit) {
                $count++;
            }
        }
        return $count;
    }
}
