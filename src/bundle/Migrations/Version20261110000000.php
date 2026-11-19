<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The tables of the providers (app_ai_provider, app_ai_model, app_ai_provider_siteaccess) and the request log
 * (app_ai_request_log). They were first created by hand in the host that grew the package, so no migration made
 * them and a blank project failed on the next one ("Table app_ai_provider doesn't exist").
 *
 * They are created as they were when the later migrations start from: api_key is widened by 20261112000000 and
 * app_ai_model.supports_vision is added by 20261113100000. Nothing is done on a database that already has the
 * provider table, so projects that made these tables themselves are not touched.
 */
final class Version20261110000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_ai_provider, app_ai_model, app_ai_provider_siteaccess and app_ai_request_log when they are missing.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'This migration is written for MySQL / MariaDB.');

        $tables = $this->connection->createSchemaManager();
        $providersExisted = $tables->tablesExist(['app_ai_provider']);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_ai_provider (
                id INT AUTO_INCREMENT NOT NULL,
                active_chat_model_id INT DEFAULT NULL,
                active_image_model_id INT DEFAULT NULL,
                name VARCHAR(100) NOT NULL,
                identifier VARCHAR(100) NOT NULL,
                api_key VARCHAR(255) DEFAULT NULL,
                api_url VARCHAR(255) DEFAULT NULL,
                INDEX IDX_D4C7D340930DA475 (active_chat_model_id),
                INDEX IDX_D4C7D340CE83A14F (active_image_model_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_ai_model (
                id INT AUTO_INCREMENT NOT NULL,
                provider_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                identifier VARCHAR(100) NOT NULL,
                supports_image TINYINT(1) NOT NULL,
                temperature DOUBLE PRECISION NOT NULL,
                max_tokens INT NOT NULL,
                INDEX IDX_83A22931A53A8AA (provider_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_ai_provider_siteaccess (
                siteaccess VARCHAR(100) NOT NULL,
                provider_id INT NOT NULL,
                INDEX IDX_17DFDC77A53A8AA (provider_id),
                PRIMARY KEY(provider_id, siteaccess)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_ai_request_log (
                id INT AUTO_INCREMENT NOT NULL,
                provider_identifier VARCHAR(32) NOT NULL,
                model_identifier VARCHAR(100) NOT NULL,
                siteaccess VARCHAR(100) DEFAULT NULL,
                success TINYINT(1) NOT NULL,
                latency_ms INT NOT NULL,
                error_code VARCHAR(64) DEFAULT NULL,
                tokens_in INT DEFAULT NULL,
                tokens_out INT DEFAULT NULL,
                finish_reason VARCHAR(32) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_ai_log_created (created_at),
                INDEX idx_ai_log_provider (provider_identifier),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        // Foreign keys cannot be added twice: only when this migration made the tables.
        if (!$providersExisted) {
            $this->addSql('ALTER TABLE app_ai_provider_siteaccess ADD CONSTRAINT FK_17DFDC77A53A8AA FOREIGN KEY (provider_id) REFERENCES app_ai_provider (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE app_ai_provider ADD CONSTRAINT FK_D4C7D340930DA475 FOREIGN KEY (active_chat_model_id) REFERENCES app_ai_model (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE app_ai_provider ADD CONSTRAINT FK_D4C7D340CE83A14F FOREIGN KEY (active_image_model_id) REFERENCES app_ai_model (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE app_ai_model ADD CONSTRAINT FK_83A22931A53A8AA FOREIGN KEY (provider_id) REFERENCES app_ai_provider (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // Not dropped: the tables may predate the migration and hold the providers and the log of the project.
        $this->throwIrreversibleMigrationException('The provider tables may hold data that existed before this migration.');
    }
}
