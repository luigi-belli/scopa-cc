<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Input\PlayCardInput;
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

final class PlayCardProcessor implements ProcessorInterface
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
        if (!$data instanceof PlayCardInput) {
            throw new BadRequestHttpException('Invalid input');
        }

        $game = $this->authenticator->loadGame($uriVariables);
        $playerIndex = $this->authenticator->authenticate($game);

        if ($game->getState() !== GameState::Playing) {
            throw new BadRequestHttpException('Game is not in playing state');
        }

        if ($game->getCurrentPlayer() !== $playerIndex) {
            throw new BadRequestHttpException('It is not your turn');
        }

        $result = $this->gameEngine->playCard($game, $playerIndex, $data->cardIndex);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('Conflict, please retry');
        }

        $gameId = (string) $game->getId();

        if ($result['type'] === 'choosing') {
            // Publish choose-capture to the current player
            $this->mercurePublisher->publishChooseCapture($gameId, $playerIndex, $result['options']);
            $this->mercurePublisher->publishGameState($gameId, $game, $this->gameEngine);
        } else {
            $this->mercurePublisher->publishTurnOutcome($gameId, $game, $this->gameEngine, $result);

            // If AI game and it's AI's turn, dispatch AI
            if ($game->isSinglePlayer() && $game->getState() === GameState::Playing && $game->getCurrentPlayer() === 1) {
                $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
            }
        }

        return $this->gameEngine->getStateForPlayer($game, $playerIndex);
    }
}
