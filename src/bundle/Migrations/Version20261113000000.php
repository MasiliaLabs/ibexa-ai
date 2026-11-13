<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Conversations of the content agent: one row per conversation, with its owner, title and last use. Until now a
 * user had a single conversation per siteaccess, stored under the id "agent-<user id>-<siteaccess>": each of them
 * becomes a row, so nothing already written is lost.
 */
final class Version20261113000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_ai_agent_thread (conversation history) and register the existing conversations in it.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'This table is created for MySQL / MariaDB.');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_ai_agent_thread (
                id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
                user_id INT NOT NULL,
                siteaccess VARCHAR(100) NOT NULL,
                title VARCHAR(160) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_ai_thread_user (user_id, siteaccess, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        // "agent-<user id>-<siteaccess>": the user id is between the first and the second dash.
        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO app_ai_agent_thread (id, user_id, siteaccess, title, created_at, updated_at)
            SELECT
                legacy.id,
                CAST(SUBSTRING_INDEX(SUBSTRING(legacy.id, 7), '-', 1) AS UNSIGNED),
                SUBSTRING(legacy.id, 6 + LENGTH(SUBSTRING_INDEX(SUBSTRING(legacy.id, 7), '-', 1)) + 2),
                'Previous conversation',
                legacy.created_at,
                legacy.updated_at
            FROM (
                SELECT CONVERT(thread_id USING utf8mb4) AS id, MIN(created_at) AS created_at, MAX(updated_at) AS updated_at
                FROM app_ai_agent_messages
                WHERE CONVERT(thread_id USING utf8mb4) REGEXP '^agent-[0-9]+-.+$'
                GROUP BY thread_id
            ) AS legacy
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_ai_agent_thread');
    }
}
