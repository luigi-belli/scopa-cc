<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Input\SelectCaptureInput;
use App\Dto\Output\GameStateOutput;
use App\Enum\GameState;
use App\Message\HandleAITurnMessage;
use App\Service\GameEngineFactory;
use App\Service\MercurePublisher;
use App\Service\PlayerAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/** @implements ProcessorInterface<mixed, GameStateOutput> */
final readonly class SelectCaptureProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlayerAuthenticator $authenticator,
        private GameEngineFactory $engineFactory,
        private MercurePublisher $mercurePublisher,
        private MessageBusInterface $messageBus,
    ) {}

    #[\Override]
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
        if ($pending === null || $pending->playerIndex !== $playerIndex) {
            throw new BadRequestHttpException('No pending capture for this player');
        }

        $engine = $this->engineFactory->forGame($game);
        $result = $engine->selectCapture($game, $data->optionIndex);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('error.conflict');
        }

        $gameId = (string) $game->getId();

        $this->mercurePublisher->publishTurnOutcome($gameId, $game, $engine, $result);

        if ($game->isSinglePlayer() && $game->getState() === GameState::Playing && $game->getCurrentPlayer() === 1) {
            $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
        }

        return $engine->getStateForPlayer($game, $playerIndex);
    }
}
