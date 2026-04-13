<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Input\CreateGameInput;
use App\Dto\Output\CreateGameOutput;
use App\Entity\Game;
use App\Enum\GameState;
use App\Message\HandleAITurnMessage;
use App\Service\GameEngineFactory;
use App\Service\MercureTokenService;
use App\Service\PlayerTokenService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/** @implements ProcessorInterface<mixed, CreateGameOutput> */
final readonly class CreateGameProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlayerTokenService $tokenService,
        private GameEngineFactory $engineFactory,
        private MessageBusInterface $messageBus,
        private MercureTokenService $mercureTokenService,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CreateGameOutput
    {
        if (!$data instanceof CreateGameInput) {
            throw new BadRequestHttpException('Invalid input');
        }

        $playerName = $this->tokenService->sanitizeName($data->playerName);
        if ($playerName === '') {
            throw new BadRequestHttpException('Player name is required');
        }

        $game = new Game();
        $token = $this->tokenService->generateToken();

        $game->setPlayer1Name($playerName);
        $game->setPlayer1Token($token);
        $game->setDeckStyle($data->deckStyle);
        $game->setLastHeartbeat1(new \DateTimeImmutable());

        $game->setGameType($data->gameType);

        if ($data->singlePlayer) {
            $game->setSinglePlayer(true);
            $game->setPlayer2Name('Claude');
            $game->setPlayer2Token($this->tokenService->generateToken());
            $game->setLastHeartbeat2(new \DateTimeImmutable());

            $engine = $this->engineFactory->forGame($game);
            $engine->initializeGame($game);
            $engine->startGame($game);
        } else {
            if ($data->gameName === null || trim($data->gameName) === '') {
                throw new BadRequestHttpException('Game name is required for multiplayer');
            }

            $gameName = mb_strtolower($this->tokenService->sanitizeName($data->gameName, maxLength: 60));

            // Check for duplicate name (unique partial index on name WHERE NOT NULL)
            $existing = $this->entityManager->getRepository(Game::class)->findOneBy(['name' => $gameName]);
            if ($existing !== null) {
                throw new ConflictHttpException('error.gameNameTaken');
            }

            $game->setName($gameName);
            $game->setState(GameState::Waiting);
        }

        $this->entityManager->persist($game);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('error.conflict');
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('error.gameNameTaken');
        }

        // If single player and AI goes first, dispatch AI turn
        if ($data->singlePlayer && $game->getCurrentPlayer() === 1) {
            $this->messageBus->dispatch(new HandleAITurnMessage((string) $game->getId()));
        }

        $gameState = null;
        if ($data->singlePlayer) {
            $engine = $this->engineFactory->forGame($game);
            $gameState = $engine->getStateForPlayer($game, 0);
        }

        $gameId = (string) $game->getId();

        return new CreateGameOutput(
            gameId: $gameId,
            playerToken: $token,
            state: $game->getState()->value,
            gameType: $game->getGameType(),
            gameState: $gameState,
            mercureToken: $this->mercureTokenService->generateSubscriberToken($gameId, 0),
        );
    }
}
