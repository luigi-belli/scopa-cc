<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PlayerAuthenticator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
    ) {}

    /** @param array<string, mixed> $uriVariables */
    public function loadGame(array $uriVariables): Game
    {
        $id = $uriVariables['id'] ?? null;
        if ($id === null) {
            throw new NotFoundHttpException('Game not found');
        }

        $game = $this->entityManager->getRepository(Game::class)->find($id);
        if ($game === null) {
            throw new NotFoundHttpException('Game not found');
        }

        return $game;
    }

    public function authenticate(Game $game): int
    {
        $request = $this->requestStack->getCurrentRequest();
        $token = $request?->headers->get('X-Player-Token');
        if ($token === null) {
            throw new AccessDeniedHttpException('Missing player token');
        }

        $playerIndex = $game->resolvePlayerIndex($token);
        if ($playerIndex === null) {
            throw new AccessDeniedHttpException('Invalid player token');
        }

        return $playerIndex;
    }

    /**
     * Authenticate with fallback to query parameter (for sendBeacon compatibility).
     */
    public function authenticateWithQueryFallback(Game $game): int
    {
        $request = $this->requestStack->getCurrentRequest();
        $token = $request?->headers->get('X-Player-Token');
        if ($token === null) {
            $token = $request?->query->get('token');
        }
        if ($token === null) {
            throw new AccessDeniedHttpException('Missing player token');
        }

        $playerIndex = $game->resolvePlayerIndex($token);
        if ($playerIndex === null) {
            throw new AccessDeniedHttpException('Invalid player token');
        }

        return $playerIndex;
    }
}
