<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025195911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tolte le postazioni';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25E29BB7D');
        $this->addSql('DROP INDEX IDX_527EDB25E29BB7D ON task');
        $this->addSql('ALTER TABLE task DROP workstation_id');
        $this->addSql('DROP TABLE workstation');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE workstation (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, description VARCHAR(120) DEFAULT NULL, sort INT DEFAULT 0 NOT NULL, cancel INT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE task ADD workstation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25E29BB7D FOREIGN KEY (workstation_id) REFERENCES workstation (id)');
        $this->addSql('CREATE INDEX IDX_527EDB25E29BB7D ON task (workstation_id)');
    }
}
