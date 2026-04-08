<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Enum\GameState;
use App\Message\HandleAITurnMessage;
use App\Service\AIServiceFactory;
use App\Service\GameEngineFactory;
use App\Service\MercurePublisher;
use App\ValueObject\TurnResultType;
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
        private readonly GameEngineFactory $engineFactory,
        private readonly AIServiceFactory $aiFactory,
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

        if ($game->getState() !== GameState::Playing) {
            return;
        }

        if ($game->getCurrentPlayer() !== self::AI_PLAYER_INDEX) {
            return;
        }

        $gameId = (string) $game->getId();
        $engine = $this->engineFactory->forGame($game);
        $aiService = $this->aiFactory->forGame($game);

        $move = $aiService->evaluateMove($game, self::AI_PLAYER_INDEX);

        $result = $engine->playCard($game, self::AI_PLAYER_INDEX, $move->cardIndex);

        if ($result->type === TurnResultType::Choosing) {
            $optionIndex = $aiService->autoSelectCapture($game);
            $result = $engine->selectCapture($game, $optionIndex);
        }

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            $this->logger->warning('AI turn optimistic lock conflict for game {gameId}, retrying.', ['gameId' => $gameId]);
            $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
            return;
        }

        $this->mercurePublisher->publishTurnOutcome($gameId, $game, $engine, $result);

        if ($game->getState() === GameState::Playing && $game->getCurrentPlayer() === self::AI_PLAYER_INDEX) {
            $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
        }
    }
}
