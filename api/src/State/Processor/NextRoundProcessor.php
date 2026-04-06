<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
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

final class NextRoundProcessor implements ProcessorInterface
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
        $game = $this->authenticator->loadGame($uriVariables);
        $playerIndex = $this->authenticator->authenticate($game);

        if ($game->getState() !== GameState::RoundEnd) {
            throw new BadRequestHttpException('Game is not in round-end state');
        }

        $this->gameEngine->nextRound($game);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('Conflict, please retry');
        }

        $gameId = (string) $game->getId();
        $this->mercurePublisher->publishGameState($gameId, $game, $this->gameEngine);

        // If single player and AI goes first, dispatch
        if ($game->isSinglePlayer() && $game->getCurrentPlayer() === 1) {
            $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
        }

        return $this->gameEngine->getStateForPlayer($game, $playerIndex);
    }
}
