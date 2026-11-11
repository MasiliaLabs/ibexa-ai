<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tables of the content agent: conversation messages and suspended workflow state.
 *
 * Column layout is the one Neuron's PDO stores expect (see SQLMessageStore and DatabasePersistence).
 */
final class Version20261111000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the content agent message and workflow tables (app_ai_agent_messages, app_ai_agent_workflow).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'These tables are created for MySQL / MariaDB.');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_ai_agent_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                thread_id VARBINARY(255) NOT NULL,
                message_id VARBINARY(64) NOT NULL,
                role VARCHAR(32) NOT NULL,
                content LONGTEXT NULL,
                meta LONGTEXT NULL,
                archived_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX idx_thread_message (thread_id, message_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_ai_agent_workflow (
                `partition` VARCHAR(510) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `key` VARCHAR(510) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `value` LONGTEXT CHARACTER SET ascii NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`partition`, `key`)
            ) ENGINE=InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_ai_agent_workflow');
        $this->addSql('DROP TABLE IF EXISTS app_ai_agent_messages');
    }
}
