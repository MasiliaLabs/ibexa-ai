<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The audit log of the assistant (app_ai_audit): what it was asked to change, by whom, with what result.
 */
final class Version20261114000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_ai_audit.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'This table is created for MySQL / MariaDB.');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_ai_audit (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                thread_id VARCHAR(64) CHARACTER SET ascii NULL,
                capability VARCHAR(80) CHARACTER SET ascii NOT NULL,
                risk VARCHAR(12) CHARACTER SET ascii NOT NULL,
                arguments LONGTEXT NOT NULL,
                success TINYINT(1) NOT NULL,
                message VARCHAR(500) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_ai_audit_created (created_at),
                INDEX idx_ai_audit_user (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_ai_audit');
    }
}
