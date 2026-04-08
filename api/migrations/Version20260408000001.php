<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multi-game support: game_type, briscola_card, last_trick, trick_leader columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE games ADD COLUMN game_type VARCHAR(20) NOT NULL DEFAULT 'scopa'");
        $this->addSql('ALTER TABLE games ADD COLUMN briscola_card JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE games ADD COLUMN last_trick JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE games ADD COLUMN trick_leader INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_game_type ON games (game_type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_game_type');
        $this->addSql('ALTER TABLE games DROP COLUMN trick_leader');
        $this->addSql('ALTER TABLE games DROP COLUMN last_trick');
        $this->addSql('ALTER TABLE games DROP COLUMN briscola_card');
        $this->addSql('ALTER TABLE games DROP COLUMN game_type');
    }
}
