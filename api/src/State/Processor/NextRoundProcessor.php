<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
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
final readonly class NextRoundProcessor implements ProcessorInterface
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
        $game = $this->authenticator->loadGame($uriVariables);
        $playerIndex = $this->authenticator->authenticate($game);

        if ($game->getState() !== GameState::RoundEnd) {
            throw new BadRequestHttpException('Game is not in round-end state');
        }

        $engine = $this->engineFactory->forGame($game);
        $engine->nextRound($game);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('error.conflict');
        }

        $gameId = (string) $game->getId();
        $this->mercurePublisher->publishGameState($gameId, $game, $engine);

        // If single player and AI goes first, dispatch
        if ($game->isSinglePlayer() && $game->getCurrentPlayer() === 1) {
            $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
        }

        return $engine->getStateForPlayer($game, $playerIndex);
    }
}
