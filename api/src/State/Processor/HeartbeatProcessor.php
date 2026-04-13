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
final readonly class HeartbeatProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlayerAuthenticator $authenticator,
        private MercurePublisher $mercurePublisher,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $game = $this->authenticator->loadGame($uriVariables);
        $playerIndex = $this->authenticator->authenticate($game);

        $now = new \DateTimeImmutable();

        if ($playerIndex === 0) {
            $game->setLastHeartbeat1($now);
        } else {
            $game->setLastHeartbeat2($now);
        }

        // Check opponent's heartbeat
        if (!$game->isSinglePlayer()) {
            $opponentHeartbeat = $playerIndex === 0 ? $game->getLastHeartbeat2() : $game->getLastHeartbeat1();
            if ($opponentHeartbeat !== null) {
                $diff = $now->getTimestamp() - $opponentHeartbeat->getTimestamp();
                if ($diff > 30 && !$game->getState()->isTerminal()) {
                    $game->setState(GameState::Finished);
                    $gameId = (string) $game->getId();
                    $this->mercurePublisher->publishOpponentDisconnected($gameId, $playerIndex);
                }
            }
        }

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('error.conflict');
        }

        return null;
    }
}
