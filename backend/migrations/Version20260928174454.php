<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928174454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stock moved to Rocket Stock: drops stock_item, stock_level and cleaning_task.stock_reports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_level DROP CONSTRAINT fk_6fad0e2da6a219');
        $this->addSql('ALTER TABLE stock_level DROP CONSTRAINT fk_6fad0e2126f525e');
        $this->addSql('DROP TABLE stock_item');
        $this->addSql('DROP TABLE stock_level');
        $this->addSql('ALTER TABLE cleaning_task DROP stock_reports');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock_item (id UUID NOT NULL, name VARCHAR(120) NOT NULL, asin VARCHAR(32) DEFAULT NULL, reorder_qty INT NOT NULL, subscription BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE stock_level (id UUID NOT NULL, level VARCHAR(8) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, place_id UUID NOT NULL, item_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_6fad0e2126f525e ON stock_level (item_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_stock_level_place_item ON stock_level (place_id, item_id)');
        $this->addSql('CREATE INDEX idx_6fad0e2da6a219 ON stock_level (place_id)');
        $this->addSql('ALTER TABLE stock_level ADD CONSTRAINT fk_6fad0e2da6a219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stock_level ADD CONSTRAINT fk_6fad0e2126f525e FOREIGN KEY (item_id) REFERENCES stock_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cleaning_task ADD stock_reports JSON DEFAULT \'[]\' NOT NULL');
    }
}
