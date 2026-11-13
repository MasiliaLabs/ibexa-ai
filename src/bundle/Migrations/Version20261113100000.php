<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Images attached to the messages of the content agent (app_ai_attachment), and whether a model can read images
 * (app_ai_model.supports_vision: not the same as supports_image, which is for generating them).
 */
final class Version20261113100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_ai_attachment and add app_ai_model.supports_vision.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'This migration is written for MySQL / MariaDB.');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_ai_attachment (
                id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(160) NOT NULL,
                mime VARCHAR(40) NOT NULL,
                size INT NOT NULL,
                width INT NOT NULL,
                height INT NOT NULL,
                filename VARCHAR(80) CHARACTER SET ascii NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_ai_attachment_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $this->addSql('ALTER TABLE app_ai_model ADD supports_vision TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_ai_model DROP supports_vision');
        $this->addSql('DROP TABLE IF EXISTS app_ai_attachment');
    }
}
