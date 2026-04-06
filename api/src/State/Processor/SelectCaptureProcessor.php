<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Input\SelectCaptureInput;
use App\Dto\Output\GameStateOutput;
use App\Enum\GameState;
use App\Message\HandleAITurnMessage;
use App\Service\GameEngine;
use App\Service\MercurePublisher;
use App\Service\PlayerAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class SelectCaptureProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlayerAuthenticator $authenticator,
        private readonly GameEngine $gameEngine,
        private readonly MercurePublisher $mercurePublisher,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GameStateOutput
    {
        if (!$data instanceof SelectCaptureInput) {
            throw new BadRequestHttpException('Invalid input');
        }

        $game = $this->authenticator->loadGame($uriVariables);
        $playerIndex = $this->authenticator->authenticate($game);

        if ($game->getState() !== GameState::Choosing) {
            throw new BadRequestHttpException('Game is not in choosing state');
        }

        $pending = $game->getPendingPlay();
        if ($pending === null || $pending['playerIndex'] !== $playerIndex) {
            throw new BadRequestHttpException('No pending capture for this player');
        }

        $result = $this->gameEngine->selectCapture($game, $data->optionIndex);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('Conflict, please retry');
        }

        $gameId = (string) $game->getId();

        $this->mercurePublisher->publishTurnOutcome($gameId, $game, $this->gameEngine, $result);

        // If AI game and AI's turn, dispatch
        if ($game->isSinglePlayer() && $game->getState() === GameState::Playing && $game->getCurrentPlayer() === 1) {
            $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
        }

        return $this->gameEngine->getStateForPlayer($game, $playerIndex);
    }
}
