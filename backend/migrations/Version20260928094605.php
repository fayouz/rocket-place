<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928094605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_grant (id UUID NOT NULL, label VARCHAR(120) NOT NULL, code VARCHAR(6) NOT NULL, valid_from TIMESTAMP(0) WITH TIME ZONE NOT NULL, valid_until TIMESTAMP(0) WITH TIME ZONE NOT NULL, status VARCHAR(16) NOT NULL, external_ref VARCHAR(120) DEFAULT NULL, error TEXT DEFAULT NULL, sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, place_id UUID NOT NULL, lock_id BIGINT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_access_grant_lock_code ON access_grant (lock_id, code)');
        $this->addSql('CREATE INDEX IDX_20901A1FDA6A219 ON access_grant (place_id)');
        $this->addSql('CREATE INDEX IDX_20901A1F836D25DD ON access_grant (lock_id)');
        $this->addSql('CREATE TABLE connector (id UUID NOT NULL, plugin_id VARCHAR(40) NOT NULL, name VARCHAR(120) NOT NULL, config JSON NOT NULL, enabled BOOLEAN NOT NULL, last_run_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_result VARCHAR(300) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, place_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_148C456EDA6A219 ON connector (place_id)');
        $this->addSql('CREATE TABLE place (id UUID NOT NULL, name VARCHAR(120) NOT NULL, address VARCHAR(255) DEFAULT NULL, color VARCHAR(16) NOT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, cloud_folder_id VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE smart_lock (nuki_id BIGINT NOT NULL, name VARCHAR(120) NOT NULL, external_id VARCHAR(120) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, place_id UUID DEFAULT NULL, connector_id UUID DEFAULT NULL, code_connector_id UUID DEFAULT NULL, PRIMARY KEY (nuki_id))');
        $this->addSql('CREATE INDEX IDX_B85502E6DA6A219 ON smart_lock (place_id)');
        $this->addSql('CREATE INDEX IDX_B85502E64D085745 ON smart_lock (connector_id)');
        $this->addSql('CREATE INDEX IDX_B85502E68620A520 ON smart_lock (code_connector_id)');
        $this->addSql('CREATE TABLE stock_item (id UUID NOT NULL, name VARCHAR(120) NOT NULL, asin VARCHAR(32) DEFAULT NULL, reorder_qty INT NOT NULL, subscription BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE stock_level (id UUID NOT NULL, level VARCHAR(8) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, place_id UUID NOT NULL, item_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_stock_level_place_item ON stock_level (place_id, item_id)');
        $this->addSql('CREATE INDEX IDX_6FAD0E2DA6A219 ON stock_level (place_id)');
        $this->addSql('CREATE INDEX IDX_6FAD0E2126F525E ON stock_level (item_id)');
        $this->addSql('ALTER TABLE access_grant ADD CONSTRAINT FK_20901A1FDA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE access_grant ADD CONSTRAINT FK_20901A1F836D25DD FOREIGN KEY (lock_id) REFERENCES smart_lock (nuki_id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE connector ADD CONSTRAINT FK_148C456EDA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE smart_lock ADD CONSTRAINT FK_B85502E6DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE smart_lock ADD CONSTRAINT FK_B85502E64D085745 FOREIGN KEY (connector_id) REFERENCES connector (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE smart_lock ADD CONSTRAINT FK_B85502E68620A520 FOREIGN KEY (code_connector_id) REFERENCES connector (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_level ADD CONSTRAINT FK_6FAD0E2DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_level ADD CONSTRAINT FK_6FAD0E2126F525E FOREIGN KEY (item_id) REFERENCES stock_item (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_grant DROP CONSTRAINT FK_20901A1FDA6A219');
        $this->addSql('ALTER TABLE access_grant DROP CONSTRAINT FK_20901A1F836D25DD');
        $this->addSql('ALTER TABLE connector DROP CONSTRAINT FK_148C456EDA6A219');
        $this->addSql('ALTER TABLE smart_lock DROP CONSTRAINT FK_B85502E6DA6A219');
        $this->addSql('ALTER TABLE smart_lock DROP CONSTRAINT FK_B85502E64D085745');
        $this->addSql('ALTER TABLE smart_lock DROP CONSTRAINT FK_B85502E68620A520');
        $this->addSql('ALTER TABLE stock_level DROP CONSTRAINT FK_6FAD0E2DA6A219');
        $this->addSql('ALTER TABLE stock_level DROP CONSTRAINT FK_6FAD0E2126F525E');
        $this->addSql('DROP TABLE access_grant');
        $this->addSql('DROP TABLE connector');
        $this->addSql('DROP TABLE place');
        $this->addSql('DROP TABLE smart_lock');
        $this->addSql('DROP TABLE stock_item');
        $this->addSql('DROP TABLE stock_level');
    }
}
