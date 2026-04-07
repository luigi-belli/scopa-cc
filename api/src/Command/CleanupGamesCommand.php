<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Enum\GameState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-games',
    description: 'Delete games that have been inactive for more than 10 minutes',
)]
final class CleanupGamesCommand extends Command
{
    private const INACTIVE_THRESHOLD_MINUTES = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = new \DateTimeImmutable(sprintf('-%d minutes', self::INACTIVE_THRESHOLD_MINUTES));

        /** @var int|string $deletedFinished */
        $deletedFinished = $this->entityManager->createQueryBuilder()
            ->delete(Game::class, 'g')
            ->where('g.state IN (:finishedStates)')
            ->setParameter('finishedStates', [GameState::Finished->value, GameState::GameOver->value])
            ->getQuery()
            ->execute();
        $deletedFinished = (int) $deletedFinished;

        /** @var int|string $deletedInactive */
        $deletedInactive = $this->entityManager->createQueryBuilder()
            ->delete(Game::class, 'g')
            ->where('g.createdAt < :threshold')
            ->andWhere('g.lastHeartbeat1 IS NULL OR g.lastHeartbeat1 < :threshold')
            ->andWhere('g.lastHeartbeat2 IS NULL OR g.lastHeartbeat2 < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
        $deletedInactive = (int) $deletedInactive;

        $total = $deletedFinished + $deletedInactive;
        if ($total > 0) {
            $io->success(sprintf('Cleaned up %d game(s) (%d finished, %d inactive).', $total, $deletedFinished, $deletedInactive));
        }

        return Command::SUCCESS;
    }
}
