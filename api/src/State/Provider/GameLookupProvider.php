<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Output\GameLookupOutput;
use App\Entity\Game;
use App\Enum\GameState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class GameLookupProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * @return list<GameLookupOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $name = trim((string) $request?->query->get('name', ''));

        if ($name === '') {
            return [];
        }

        $game = $this->entityManager->getRepository(Game::class)->findOneBy([
            'name' => $name,
            'state' => GameState::Waiting,
        ]);

        if ($game === null) {
            return [];
        }

        return [new GameLookupOutput(
            id: (string) $game->getId(),
            name: $game->getName() ?? '',
            state: $game->getState()->value,
        )];
    }
}
