<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add player scopa cards tracking columns for resilient scopa marker display';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE games ADD COLUMN player1_scopa_cards JSONB NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE games ADD COLUMN player2_scopa_cards JSONB NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE games DROP COLUMN player2_scopa_cards');
        $this->addSql('ALTER TABLE games DROP COLUMN player1_scopa_cards');
    }
}
