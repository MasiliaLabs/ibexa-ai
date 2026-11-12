<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The provider API key is now stored encrypted (enc:v1: + base64), which is longer than the key itself and
 * does not fit in 255 characters. Existing keys are encrypted by `masilia:ai:encrypt-keys`.
 */
final class Version20261112000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen app_ai_provider.api_key so it can hold the encrypted key.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'This migration is written for MySQL / MariaDB.');

        $this->addSql('ALTER TABLE app_ai_provider MODIFY api_key LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $tooLong = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_ai_provider WHERE CHAR_LENGTH(api_key) > 255');
        $this->abortIf($tooLong > 0, 'Some provider keys are longer than 255 characters (encrypted keys are): they would be cut. Enter them again before going back.');

        $this->addSql('ALTER TABLE app_ai_provider MODIFY api_key VARCHAR(255) DEFAULT NULL');
    }
}
