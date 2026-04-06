<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Output\GameStateOutput;
use App\Service\GameEngine;
use App\Service\PlayerAuthenticator;

final class GameStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly PlayerAuthenticator $authenticator,
        private readonly GameEngine $gameEngine,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GameStateOutput
    {
        $game = $this->authenticator->loadGame($uriVariables);
        $playerIndex = $this->authenticator->authenticate($game);

        return $this->gameEngine->getStateForPlayer($game, $playerIndex);
    }
}
