<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Enum\GameState;
use App\Message\HandleAITurnMessage;
use App\Service\AIService;
use App\Service\GameEngine;
use App\Service\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class HandleAITurnHandler
{
    private const int AI_PLAYER_INDEX = 1;
    private const int AI_DELAY_MICROSECONDS = 1_500_000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameEngine $gameEngine,
        private readonly AIService $aiService,
        private readonly MercurePublisher $mercurePublisher,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(HandleAITurnMessage $message): void
    {
        usleep(self::AI_DELAY_MICROSECONDS);

        $game = $this->entityManager->getRepository(Game::class)->find($message->gameId);
        if ($game === null) {
            return;
        }

        // Verify game is still active and it's AI's turn
        if ($game->getState() !== GameState::Playing) {
            return;
        }

        if ($game->getCurrentPlayer() !== self::AI_PLAYER_INDEX) {
            return;
        }

        $gameId = (string) $game->getId();

        // Evaluate best move
        $move = $this->aiService->evaluateMove($game, self::AI_PLAYER_INDEX);

        // Play the card
        $result = $this->gameEngine->playCard($game, self::AI_PLAYER_INDEX, $move['cardIndex']);

        if ($result['type'] === 'choosing') {
            // AI auto-selects capture
            $optionIndex = $this->aiService->autoSelectCapture($game);
            $result = $this->gameEngine->selectCapture($game, $optionIndex);
        }

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            $this->logger->warning('AI turn optimistic lock conflict for game {gameId}, retrying.', ['gameId' => $gameId]);
            $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
            return;
        }

        // Publish turn result, then appropriate game state event
        $this->mercurePublisher->publishTurnOutcome($gameId, $game, $this->gameEngine, $result);

        // If it's still AI's turn (after re-deal), dispatch another message
        if ($game->getState() === GameState::Playing && $game->getCurrentPlayer() === self::AI_PLAYER_INDEX) {
            $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
        }
    }
}
