<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919115907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__experience AS SELECT id, written_at, text FROM experience');
        $this->addSql('DROP TABLE experience');
        $this->addSql('CREATE TABLE experience (id INTEGER NOT NULL, written_at DATE NOT NULL, text CLOB NOT NULL, intention VARCHAR(255) DEFAULT NULL, intention_confidence DOUBLE PRECISION DEFAULT NULL, priority DOUBLE PRECISION DEFAULT NULL, bug_probability DOUBLE PRECISION DEFAULT NULL, triage_tokens INTEGER DEFAULT NULL, triage_duration_ms INTEGER DEFAULT NULL, triage_requested_at DATETIME DEFAULT NULL, triaged_at DATETIME DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO experience (id, written_at, text) SELECT id, written_at, text FROM __temp__experience');
        $this->addSql('DROP TABLE __temp__experience');
        $this->addSql('CREATE INDEX experience_recent_first_idx ON experience (written_at, id)');
        $this->addSql('CREATE INDEX experience_triage_requested_idx ON experience (triage_requested_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__experience AS SELECT id, written_at, text FROM experience');
        $this->addSql('DROP TABLE experience');
        $this->addSql('CREATE TABLE experience (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, written_at DATE NOT NULL, text CLOB NOT NULL)');
        $this->addSql('INSERT INTO experience (id, written_at, text) SELECT id, written_at, text FROM __temp__experience');
        $this->addSql('DROP TABLE __temp__experience');
        $this->addSql('CREATE INDEX experience_recent_first_idx ON experience (written_at, id)');
    }
}
