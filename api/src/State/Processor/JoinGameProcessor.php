<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Input\JoinGameInput;
use App\Dto\Output\JoinGameOutput;
use App\Entity\Game;
use App\Enum\GameState;
use App\Service\GameEngineFactory;
use App\Service\MercurePublisher;
use App\Service\MercureTokenService;
use App\Service\PlayerTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<mixed, JoinGameOutput> */
final class JoinGameProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlayerTokenService $tokenService,
        private readonly GameEngineFactory $engineFactory,
        private readonly MercurePublisher $mercurePublisher,
        private readonly MercureTokenService $mercureTokenService,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JoinGameOutput
    {
        if (!$data instanceof JoinGameInput) {
            throw new BadRequestHttpException('Invalid input');
        }

        $id = $uriVariables['id'] ?? null;
        $game = $this->entityManager->getRepository(Game::class)->find($id);

        if ($game === null) {
            throw new NotFoundHttpException('Game not found');
        }

        if ($game->getState() !== GameState::Waiting) {
            throw new BadRequestHttpException('Game is not waiting for players');
        }

        if ($game->getPlayer2Token() !== null) {
            throw new BadRequestHttpException('Game is already full');
        }

        $playerName = $this->tokenService->sanitizeName($data->playerName);
        if ($playerName === '') {
            throw new BadRequestHttpException('Player name is required');
        }

        $token = $this->tokenService->generateToken();
        $game->setPlayer2Name($playerName);
        $game->setPlayer2Token($token);
        $game->setLastHeartbeat2(new \DateTimeImmutable());

        $engine = $this->engineFactory->forGame($game);
        $engine->initializeGame($game);
        $engine->startGame($game);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('error.conflict');
        }

        $gameId = (string) $game->getId();

        // Publish game state to player 1 (waiting player)
        $this->mercurePublisher->publishGameState($gameId, $game, $engine);

        $gameState = $engine->getStateForPlayer($game, 1);

        return new JoinGameOutput(
            gameId: $gameId,
            playerToken: $token,
            gameState: $gameState,
            mercureToken: $this->mercureTokenService->generateSubscriberToken($gameId, 1),
        );
    }
}
