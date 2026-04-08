<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Output\GameStateOutput;
use App\Service\GameEngineFactory;
use App\Service\MercureTokenService;
use App\Service\PlayerAuthenticator;

/** @implements ProviderInterface<GameStateOutput> */
final class GameStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly PlayerAuthenticator $authenticator,
        private readonly GameEngineFactory $engineFactory,
        private readonly MercureTokenService $mercureTokenService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GameStateOutput
    {
        $game = $this->authenticator->loadGame($uriVariables);
        $playerIndex = $this->authenticator->authenticate($game);

        $engine = $this->engineFactory->forGame($game);

        return $engine->getStateForPlayer($game, $playerIndex)->withMercureToken(
            $this->mercureTokenService->generateSubscriberToken(
                (string) $game->getId(),
                $playerIndex,
            ),
        );
    }
}
