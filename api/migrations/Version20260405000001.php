<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create games table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE games (
            id UUID NOT NULL,
            name VARCHAR(60) DEFAULT NULL,
            state VARCHAR(20) NOT NULL,
            player1_token VARCHAR(64) DEFAULT NULL,
            player2_token VARCHAR(64) DEFAULT NULL,
            player1_name VARCHAR(30) DEFAULT NULL,
            player2_name VARCHAR(30) DEFAULT NULL,
            player1_hand JSONB NOT NULL DEFAULT \'[]\',
            player2_hand JSONB NOT NULL DEFAULT \'[]\',
            table_cards JSONB NOT NULL DEFAULT \'[]\',
            deck JSONB NOT NULL DEFAULT \'[]\',
            current_player INT NOT NULL DEFAULT 0,
            dealer_index INT NOT NULL DEFAULT 0,
            last_capturer INT DEFAULT NULL,
            pending_play JSONB DEFAULT NULL,
            player1_captured JSONB NOT NULL DEFAULT \'[]\',
            player2_captured JSONB NOT NULL DEFAULT \'[]\',
            player1_scope INT NOT NULL DEFAULT 0,
            player2_scope INT NOT NULL DEFAULT 0,
            player1_total_score INT NOT NULL DEFAULT 0,
            player2_total_score INT NOT NULL DEFAULT 0,
            round_history JSONB NOT NULL DEFAULT \'[]\',
            deck_style VARCHAR(20) NOT NULL DEFAULT \'piacentine\',
            single_player BOOLEAN NOT NULL DEFAULT FALSE,
            version INT NOT NULL DEFAULT 1,
            last_heartbeat1 TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL,
            last_heartbeat2 TIMESTAMP(6) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX uniq_game_name ON games (name) WHERE name IS NOT NULL');
        $this->addSql('CREATE INDEX idx_game_state ON games (state)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE games');
    }
}
