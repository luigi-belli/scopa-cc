<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Input\PlayCardInput;
use App\Dto\Output\GameStateOutput;
use App\Enum\GameState;
use App\Message\HandleAITurnMessage;
use App\Service\GameEngineFactory;
use App\Service\MercurePublisher;
use App\Service\PlayerAuthenticator;
use App\ValueObject\TurnResultType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/** @implements ProcessorInterface<mixed, GameStateOutput> */
final class PlayCardProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlayerAuthenticator $authenticator,
        private readonly GameEngineFactory $engineFactory,
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

        $engine = $this->engineFactory->forGame($game);
        $result = $engine->playCard($game, $playerIndex, $data->cardIndex);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('error.conflict');
        }

        $gameId = (string) $game->getId();

        if ($result->type === TurnResultType::Choosing) {
            // Only notify the choosing player — the opponent will see the full
            // capture animation once the choice is resolved by SelectCaptureProcessor.
            // Send type 'choosing' (not 'place') so the frontend shows the capture
            // selection overlay immediately, before any card-flying animation.
            $this->mercurePublisher->publishToPlayer($gameId, $playerIndex, 'turn-result', $result->jsonSerialize());
            $this->mercurePublisher->publishGameStateToPlayer($gameId, $playerIndex, $game, $engine);
        } else {
            $this->mercurePublisher->publishTurnOutcome($gameId, $game, $engine, $result);

            if ($game->isSinglePlayer() && $game->getState() === GameState::Playing && $game->getCurrentPlayer() === 1) {
                $this->messageBus->dispatch(new HandleAITurnMessage($gameId));
            }
        }

        return $engine->getStateForPlayer($game, $playerIndex);
    }
}
