<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919114732 extends AbstractMigration
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
        $this->addSql('CREATE TABLE experience (id INTEGER NOT NULL, written_at DATE NOT NULL, text CLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO experience (id, written_at, text) SELECT id, written_at, text FROM __temp__experience');
        $this->addSql('DROP TABLE __temp__experience');
        $this->addSql('CREATE INDEX experience_recent_first_idx ON experience (written_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__experience AS SELECT id, written_at, text FROM experience');
        $this->addSql('DROP TABLE experience');
        $this->addSql('CREATE TABLE experience (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, written_at DATE NOT NULL, text CLOB NOT NULL)');
        $this->addSql('INSERT INTO experience (id, written_at, text) SELECT id, written_at, text FROM __temp__experience');
        $this->addSql('DROP TABLE __temp__experience');
    }
}
