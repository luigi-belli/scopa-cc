<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Output\GameStateOutput;
use App\Service\GameEngine;
use App\Service\MercureTokenService;
use App\Service\PlayerAuthenticator;

/** @implements ProviderInterface<GameStateOutput> */
final class GameStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly PlayerAuthenticator $authenticator,
        private readonly GameEngine $gameEngine,
        private readonly MercureTokenService $mercureTokenService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GameStateOutput
    {
        $game = $this->authenticator->loadGame($uriVariables);
        $playerIndex = $this->authenticator->authenticate($game);

        $state = $this->gameEngine->getStateForPlayer($game, $playerIndex);

        return new GameStateOutput(
            state: $state->state,
            currentPlayer: $state->currentPlayer,
            myIndex: $state->myIndex,
            myName: $state->myName,
            opponentName: $state->opponentName,
            myHand: $state->myHand,
            myCapturedCount: $state->myCapturedCount,
            myScope: $state->myScope,
            myTotalScore: $state->myTotalScore,
            opponentHandCount: $state->opponentHandCount,
            opponentCapturedCount: $state->opponentCapturedCount,
            opponentScope: $state->opponentScope,
            opponentTotalScore: $state->opponentTotalScore,
            table: $state->table,
            deckCount: $state->deckCount,
            isMyTurn: $state->isMyTurn,
            pendingChoice: $state->pendingChoice,
            roundHistory: $state->roundHistory,
            deckStyle: $state->deckStyle,
            turnResult: $state->turnResult,
            mercureToken: $this->mercureTokenService->generateSubscriberToken(
                (string) $game->getId(),
                $playerIndex,
            ),
        );
    }
}
