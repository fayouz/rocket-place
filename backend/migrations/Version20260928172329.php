<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928172329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ménage retiré (repris par Rocket Clean) : suppression de cleaning_task et cleaning_checklist_item.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cleaning_checklist_item DROP CONSTRAINT fk_8431d06eda6a219');
        $this->addSql('ALTER TABLE cleaning_task DROP CONSTRAINT fk_d2b7317059ec7d60');
        $this->addSql('ALTER TABLE cleaning_task DROP CONSTRAINT fk_d2b73170da6a219');
        $this->addSql('DROP TABLE cleaning_checklist_item');
        $this->addSql('DROP TABLE cleaning_task');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cleaning_checklist_item (id UUID NOT NULL, label VARCHAR(160) NOT NULL, "position" INT NOT NULL, place_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_8431d06eda6a219 ON cleaning_checklist_item (place_id)');
        $this->addSql('CREATE TABLE cleaning_task (id UUID NOT NULL, label VARCHAR(120) NOT NULL, scheduled_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, due_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, status VARCHAR(16) NOT NULL, external_ref VARCHAR(120) DEFAULT NULL, notes TEXT DEFAULT NULL, checklist JSON NOT NULL, photos JSON NOT NULL, stock_reports JSON NOT NULL, started_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, place_id UUID NOT NULL, assignee_id UUID DEFAULT NULL, link_salt VARCHAR(32) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_d2b7317059ec7d60 ON cleaning_task (assignee_id)');
        $this->addSql('CREATE INDEX idx_cleaning_task_scheduled_at ON cleaning_task (scheduled_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cleaning_task_place_external_ref ON cleaning_task (place_id, external_ref)');
        $this->addSql('CREATE INDEX idx_d2b73170da6a219 ON cleaning_task (place_id)');
        $this->addSql('ALTER TABLE cleaning_checklist_item ADD CONSTRAINT fk_8431d06eda6a219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cleaning_task ADD CONSTRAINT fk_d2b7317059ec7d60 FOREIGN KEY (assignee_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cleaning_task ADD CONSTRAINT fk_d2b73170da6a219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
