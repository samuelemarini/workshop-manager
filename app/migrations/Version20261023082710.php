<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023082710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, subject_type VARCHAR(20) NOT NULL, subject_id INT NOT NULL, message VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, mechanic_id INT DEFAULT NULL, INDEX IDX_FD06F647A76ED395 (user_id), INDEX IDX_FD06F6479A67DB00 (mechanic_id), INDEX IDX_FD06F6471E14D3F223EDC87 (subject_type, subject_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE attachment (id INT AUTO_INCREMENT NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(100) NOT NULL, mime_type VARCHAR(100) DEFAULT NULL, size INT NOT NULL, uploaded_at DATETIME NOT NULL, work_order_id INT DEFAULT NULL, task_id INT DEFAULT NULL, mechanic_id INT DEFAULT NULL, uploaded_by_id INT DEFAULT NULL, INDEX IDX_795FD9BB582AE764 (work_order_id), INDEX IDX_795FD9BB8DB60186 (task_id), INDEX IDX_795FD9BB9A67DB00 (mechanic_id), INDEX IDX_795FD9BBA2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, vat_number VARCHAR(20) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, zip VARCHAR(20) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, contact_person VARCHAR(150) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, contact_preference VARCHAR(20) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, cancel INT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mechanic (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, surname VARCHAR(80) DEFAULT NULL, color VARCHAR(10) DEFAULT \'red\' NOT NULL, daily_hours NUMERIC(4, 2) DEFAULT 8 NOT NULL, pin_hash VARCHAR(255) DEFAULT NULL, sort INT DEFAULT 0 NOT NULL, cancel INT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE part (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(40) DEFAULT NULL, description VARCHAR(150) NOT NULL, quantity NUMERIC(10, 2) NOT NULL, unit VARCHAR(5) DEFAULT \'pz\' NOT NULL, unit_price NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(10) DEFAULT \'STOCK\' NOT NULL, expected_at DATE DEFAULT NULL, supplier VARCHAR(100) DEFAULT NULL, work_order_id INT NOT NULL, task_id INT DEFAULT NULL, added_by_id INT DEFAULT NULL, INDEX IDX_490F70C6582AE764 (work_order_id), INDEX IDX_490F70C68DB60186 (task_id), INDEX IDX_490F70C655B127A4 (added_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(30) NOT NULL, name VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_57698A6A77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE setting (name VARCHAR(40) NOT NULL, value VARCHAR(255) NOT NULL, PRIMARY KEY (name)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, estimated_minutes INT DEFAULT 60 NOT NULL, planned_start DATETIME DEFAULT NULL, status VARCHAR(10) NOT NULL, pause_reason VARCHAR(100) DEFAULT NULL, checklist JSON NOT NULL, notes LONGTEXT DEFAULT NULL, proposed_at DATETIME DEFAULT NULL, urgent TINYINT DEFAULT 0 NOT NULL, completed_at DATETIME DEFAULT NULL, sort INT DEFAULT 0 NOT NULL, work_order_id INT NOT NULL, mechanic_id INT DEFAULT NULL, workstation_id INT DEFAULT NULL, proposed_by_id INT DEFAULT NULL, INDEX IDX_527EDB25582AE764 (work_order_id), INDEX IDX_527EDB259A67DB00 (mechanic_id), INDEX IDX_527EDB25E29BB7D (workstation_id), INDEX IDX_527EDB25DAB5A938 (proposed_by_id), INDEX IDX_527EDB25117CE21B (planned_start), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_template (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, estimated_minutes INT DEFAULT 60 NOT NULL, checklist JSON NOT NULL, sort INT DEFAULT 0 NOT NULL, cancel INT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE time_entry (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, end_reason VARCHAR(100) DEFAULT NULL, task_id INT NOT NULL, mechanic_id INT NOT NULL, INDEX IDX_6E537C0C8DB60186 (task_id), INDEX IDX_6E537C0C9A67DB00 (mechanic_id), INDEX IDX_6E537C0CD46F4E3 (started_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, name VARCHAR(180) NOT NULL, surname VARCHAR(180) NOT NULL, email VARCHAR(180) DEFAULT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT NULL, cancel INT DEFAULT 0 NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, INDEX IDX_8D93D649B03A8386 (created_by_id), INDEX IDX_8D93D649896DBBDE (updated_by_id), UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users_roles (user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_51498A8EA76ED395 (user_id), INDEX IDX_51498A8ED60322AC (role_id), PRIMARY KEY (user_id, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vehicle (id INT AUTO_INCREMENT NOT NULL, plate VARCHAR(15) NOT NULL, make VARCHAR(60) NOT NULL, model VARCHAR(80) NOT NULL, version VARCHAR(80) DEFAULT NULL, fuel VARCHAR(20) DEFAULT NULL, year INT DEFAULT NULL, vin VARCHAR(17) DEFAULT NULL, km INT DEFAULT NULL, revision_due DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, cancel INT DEFAULT 0 NOT NULL, owner_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1B80E486719ED75B (plate), INDEX IDX_1B80E4867E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE work_order (id INT AUTO_INCREMENT NOT NULL, year INT NOT NULL, number INT NOT NULL, status VARCHAR(15) NOT NULL, waiting_reason VARCHAR(255) DEFAULT NULL, accepted_at DATETIME NOT NULL, promised_at DATETIME DEFAULT NULL, ready_at DATETIME DEFAULT NULL, customer_notified_at DATETIME DEFAULT NULL, delivered_at DATETIME DEFAULT NULL, km_in INT DEFAULT NULL, fuel_level VARCHAR(10) DEFAULT NULL, customer_request LONGTEXT DEFAULT NULL, intake_notes LONGTEXT DEFAULT NULL, internal_notes LONGTEXT DEFAULT NULL, quote_amount NUMERIC(10, 2) DEFAULT NULL, courtesy_car TINYINT DEFAULT 0 NOT NULL, customer_id INT NOT NULL, vehicle_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_DDD2E8B79395C3F3 (customer_id), INDEX IDX_DDD2E8B7545317D1 (vehicle_id), INDEX IDX_DDD2E8B7B03A8386 (created_by_id), UNIQUE INDEX UNIQ_DDD2E8B7BB82733796901F54 (year, number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE workstation (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, description VARCHAR(120) DEFAULT NULL, sort INT DEFAULT 0 NOT NULL, cancel INT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F6479A67DB00 FOREIGN KEY (mechanic_id) REFERENCES mechanic (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BB582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BB8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BB9A67DB00 FOREIGN KEY (mechanic_id) REFERENCES mechanic (id)');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BBA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE part ADD CONSTRAINT FK_490F70C6582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE part ADD CONSTRAINT FK_490F70C68DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE part ADD CONSTRAINT FK_490F70C655B127A4 FOREIGN KEY (added_by_id) REFERENCES mechanic (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB259A67DB00 FOREIGN KEY (mechanic_id) REFERENCES mechanic (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25E29BB7D FOREIGN KEY (workstation_id) REFERENCES workstation (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25DAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES mechanic (id)');
        $this->addSql('ALTER TABLE time_entry ADD CONSTRAINT FK_6E537C0C8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE time_entry ADD CONSTRAINT FK_6E537C0C9A67DB00 FOREIGN KEY (mechanic_id) REFERENCES mechanic (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE users_roles ADD CONSTRAINT FK_51498A8EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users_roles ADD CONSTRAINT FK_51498A8ED60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E4867E3C61F9 FOREIGN KEY (owner_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE work_order ADD CONSTRAINT FK_DDD2E8B79395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)');
        $this->addSql('ALTER TABLE work_order ADD CONSTRAINT FK_DDD2E8B7545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id)');
        $this->addSql('ALTER TABLE work_order ADD CONSTRAINT FK_DDD2E8B7B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647A76ED395');
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F6479A67DB00');
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BB582AE764');
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BB8DB60186');
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BB9A67DB00');
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BBA2B28FE8');
        $this->addSql('ALTER TABLE part DROP FOREIGN KEY FK_490F70C6582AE764');
        $this->addSql('ALTER TABLE part DROP FOREIGN KEY FK_490F70C68DB60186');
        $this->addSql('ALTER TABLE part DROP FOREIGN KEY FK_490F70C655B127A4');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25582AE764');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB259A67DB00');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25E29BB7D');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25DAB5A938');
        $this->addSql('ALTER TABLE time_entry DROP FOREIGN KEY FK_6E537C0C8DB60186');
        $this->addSql('ALTER TABLE time_entry DROP FOREIGN KEY FK_6E537C0C9A67DB00');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649B03A8386');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649896DBBDE');
        $this->addSql('ALTER TABLE users_roles DROP FOREIGN KEY FK_51498A8EA76ED395');
        $this->addSql('ALTER TABLE users_roles DROP FOREIGN KEY FK_51498A8ED60322AC');
        $this->addSql('ALTER TABLE vehicle DROP FOREIGN KEY FK_1B80E4867E3C61F9');
        $this->addSql('ALTER TABLE work_order DROP FOREIGN KEY FK_DDD2E8B79395C3F3');
        $this->addSql('ALTER TABLE work_order DROP FOREIGN KEY FK_DDD2E8B7545317D1');
        $this->addSql('ALTER TABLE work_order DROP FOREIGN KEY FK_DDD2E8B7B03A8386');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE mechanic');
        $this->addSql('DROP TABLE part');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE setting');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_template');
        $this->addSql('DROP TABLE time_entry');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE users_roles');
        $this->addSql('DROP TABLE vehicle');
        $this->addSql('DROP TABLE work_order');
        $this->addSql('DROP TABLE workstation');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
