<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Enum\GameState;
use App\Service\MercurePublisher;
use App\Service\PlayerAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @implements ProcessorInterface<mixed, null> */
final class LeaveGameProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlayerAuthenticator $authenticator,
        private readonly MercurePublisher $mercurePublisher,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $game = $this->authenticator->loadGame($uriVariables);
        $playerIndex = $this->authenticator->authenticateWithQueryFallback($game);

        $game->setState(GameState::Finished);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('Conflict, please retry');
        }

        $opponentIndex = $playerIndex === 0 ? 1 : 0;
        $gameId = (string) $game->getId();
        $this->mercurePublisher->publishOpponentDisconnected($gameId, $opponentIndex);

        return null;
    }
}
