<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Masilia\Bundle\IbexaAi\Migrations\Version20261120000000;
use Masilia\IbexaAi\Provider\ProviderId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProvidersCatalogMigrationTest extends TestCase
{
    public function testTheCatalogIsCoherent(): void
    {
        $known = array_map(static fn (ProviderId $id): string => $id->value, ProviderId::cases());
        $this->assertEqualsCanonicalizing($known, array_keys(Version20261120000000::CATALOG), 'every provider of the assistant, and no other');

        foreach (Version20261120000000::CATALOG as $identifier => $provider) {
            $identifiers = array_column($provider['models'], 1);
            $this->assertSame(array_unique($identifiers), $identifiers, $identifier . ': a model twice');
            foreach ($provider['models'] as [$name, $modelIdentifier, , , $maxTokens]) {
                $this->assertNotSame('', $name);
                $this->assertGreaterThan(0, $maxTokens, $modelIdentifier);
                $this->assertLessThanOrEqual(100, strlen($modelIdentifier), 'the column holds 100 characters');
            }
            foreach (['chat', 'image'] as $kind) {
                if ($provider[$kind] !== null) {
                    $this->assertContains($provider[$kind], $identifiers, sprintf('%s: the active %s model is in the list', $identifier, $kind));
                }
            }
        }
    }

    public function testAnEmptyDatabaseGetsEveryProviderItsModelsAndItsActiveModels(): void
    {
        $statements = $this->statements(providers: 0);

        $inserts = array_values(array_filter($statements, static fn (array $s): bool => str_starts_with($s[0], 'INSERT INTO app_ai_provider')));
        $this->assertCount(count(Version20261120000000::CATALOG), $inserts);
        $this->assertCount(array_sum(array_map(static fn (array $p): int => count($p['models']), Version20261120000000::CATALOG)), array_filter($statements, static fn (array $s): bool => str_starts_with($s[0], 'INSERT INTO app_ai_model')));
        $this->assertNotEmpty(array_filter($statements, static fn (array $s): bool => str_starts_with($s[0], 'UPDATE app_ai_provider SET active_chat_model_id')));
        foreach ($inserts as [$sql]) {
            $this->assertStringContainsString('NULL', $sql, 'no API key');
        }
    }

    public function testAProviderThatExistsKeepsItsKeysAndItsActiveModelsOnlyModelsAreAdded(): void
    {
        $statements = $this->statements(providers: 1);

        $this->assertSame([], array_filter($statements, static fn (array $s): bool => str_starts_with($s[0], 'INSERT INTO app_ai_provider')), 'no provider is created');
        $this->assertSame([], array_filter($statements, static fn (array $s): bool => str_starts_with($s[0], 'UPDATE')), 'no active model is changed');
        foreach ($statements as [$sql]) {
            $this->assertStringContainsString('NOT EXISTS', str_contains($sql, 'INSERT INTO app_ai_model') ? $sql : 'NOT EXISTS', 'a model is added only when it is missing');
            $this->assertStringNotContainsString('DELETE', $sql);
        }
    }

    /**
     * @return list<array{0: string, 1: array<int, mixed>}>
     */
    private function statements(int $providers): array
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('fetchOne')->willReturn($providers);

        $migration = new Version20261120000000($connection, new NullLogger());
        $migration->up(new Schema());

        return array_map(static fn ($query): array => [$query->getStatement(), $query->getParameters()], $migration->getSql());
    }
}
