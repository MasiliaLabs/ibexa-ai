<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The providers of the assistant, and their current models, so that a new project starts with a catalog: it only has to give
 * an API key. The list is the one of 2026-11-20, read on the documentation of each provider; models change often, so it is only a
 * start: add and remove yours in the admin (AI Assistant).
 *
 * What it does, and only this:
 *  - a provider that does not exist (no row with its identifier) is created, without an API key;
 *  - a model that the provider does not have yet (no row with its identifier) is added;
 *  - the active chat (and image) model is set, for a provider that this migration has just created.
 * It never changes or removes a row that exists, so running it on a project that has its own providers, keys and models adds only
 * what is missing. The catalog is written here, not read from a class of the package: a migration stays what it was on the day it was written.
 */
final class Version20261120000000 extends AbstractMigration
{
    private const TEMPERATURE = 0.7;

    /**
     * Provider identifier => [name, API url (null = the default of the provider), models, active chat model, active image model].
     * A model: [name, identifier, generates images, accepts images, max tokens].
     *
     * Not listed on purpose: models whose identifier the documentation does not state (Mistral), models that depend on the machine
     * (Ollama), and the ones for which the sources disagree (Kimi K3.1, the image models of OpenAI, Gemini 4).
     */
    public const CATALOG = [
        'openai' => [
            'name' => 'OpenAI',
            'url' => null,
            'models' => [
                ['GPT-6.2 Astra', 'gpt-6.2-astra', false, true, 16384],
                ['GPT-6.2 Sol', 'gpt-6.2-sol', false, true, 16384],
                ['GPT-6.1 Terra', 'gpt-6.1-terra', false, true, 16384],
                ['GPT-6 Luna', 'gpt-6-luna', false, true, 16384],
            ],
            'chat' => 'gpt-6.2-astra',
            'image' => null,
        ],
        'anthropic' => [
            'name' => 'Anthropic',
            'url' => null,
            'models' => [
                ['Claude Fable 5.2', 'claude-fable-5-2', false, true, 16384],
                ['Claude Opus 5.5', 'claude-opus-5-5', false, true, 16384],
                ['Claude Sonnet 5.5', 'claude-sonnet-5-5', false, true, 16384],
                ['Claude Haiku 5.5', 'claude-haiku-5-5', false, true, 8192],
            ],
            'chat' => 'claude-sonnet-5-5',
            'image' => null,
        ],
        'gemini' => [
            'name' => 'Google Gemini',
            'url' => null,
            'models' => [
                ['Gemini 3.8 Flash', 'gemini-3.8-flash', false, true, 16384],
                ['Gemini 3.6 Flash', 'gemini-3.6-flash', false, true, 16384],
                ['Gemini 3.5 Flash-Lite', 'gemini-3.5-flash-lite', false, true, 8192],
                ['Nano Banana 2 (Gemini 3.1 Flash Image)', 'gemini-3.1-flash-image', true, true, 8192],
                ['Nano Banana Pro (Gemini 3 Pro Image)', 'gemini-3-pro-image', true, true, 8192],
            ],
            'chat' => 'gemini-3.8-flash',
            'image' => 'gemini-3.1-flash-image',
        ],
        'mistral' => [
            'name' => 'Mistral',
            'url' => null,
            'models' => [],
            'chat' => null,
            'image' => null,
        ],
        'ollama' => [
            'name' => 'Ollama',
            'url' => null,
            'models' => [],
            'chat' => null,
            'image' => null,
        ],
        'qwen' => [
            'name' => 'Qwen (Alibaba DashScope)',
            'url' => null,
            'models' => [
                ['Qwen3.7 Plus', 'qwen3.7-plus', false, false, 8192],
                ['Qwen3.6 Plus', 'qwen3.6-plus', false, false, 8192],
                ['Qwen3.6 Flash', 'qwen3.6-flash', false, false, 8192],
                ['Qwen3.5 Plus', 'qwen3.5-plus', false, false, 8192],
                ['Qwen3.5 Flash', 'qwen3.5-flash', false, false, 8192],
                ['Qwen Max', 'qwen-max', false, false, 8192],
                ['Qwen Plus', 'qwen-plus', false, false, 8192],
                ['Qwen Flash', 'qwen-flash', false, false, 8192],
            ],
            'chat' => 'qwen3.7-plus',
            'image' => null,
        ],
        'kimi' => [
            'name' => 'Kimi (Moonshot)',
            'url' => 'https://api.moonshot.ai/v1',
            'models' => [
                ['Kimi K3', 'kimi-k3', false, false, 16384],
            ],
            'chat' => 'kimi-k3',
            'image' => null,
        ],
        'minimax' => [
            'name' => 'MiniMax',
            'url' => 'https://api.minimax.io/v1',
            'models' => [
                ['MiniMax M3.1 Flash', 'MiniMax-M3.1-Flash', false, false, 8192],
                ['MiniMax M3', 'MiniMax-M3', false, false, 16384],
            ],
            'chat' => 'MiniMax-M3',
            'image' => null,
        ],
        'mimo' => [
            'name' => 'MiMo (Xiaomi)',
            'url' => 'https://api.xiaomimimo.com/v1',
            'models' => [
                ['MiMo V2.5 Pro', 'mimo-v2.5-pro', false, false, 16384],
                ['MiMo V2.5', 'mimo-v2.5', false, false, 8192],
            ],
            'chat' => 'mimo-v2.5-pro',
            'image' => null,
        ],
    ];

    public function getDescription(): string
    {
        return 'Adds the providers of the assistant and their current models when they are missing (nothing that exists is changed).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'This migration is written for MySQL / MariaDB.');

        foreach (self::CATALOG as $identifier => $provider) {
            // Read here (not written): the statements below are queued, so a dry run shows them without changing anything.
            $created = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_ai_provider WHERE identifier = ?', [$identifier]) === 0;

            if ($created) {
                $this->addSql(
                    'INSERT INTO app_ai_provider (name, identifier, api_key, api_url) VALUES (?, ?, NULL, ?)',
                    [$provider['name'], $identifier, $provider['url']],
                );
            }

            foreach ($provider['models'] as [$name, $modelIdentifier, $generatesImages, $acceptsImages, $maxTokens]) {
                $this->addSql(
                    'INSERT INTO app_ai_model (provider_id, name, identifier, supports_image, supports_vision, temperature, max_tokens) '
                    . 'SELECT p.id, ?, ?, ?, ?, ?, ? FROM app_ai_provider p '
                    . 'WHERE p.id = (SELECT MIN(id) FROM app_ai_provider WHERE identifier = ?) '
                    . 'AND NOT EXISTS (SELECT 1 FROM app_ai_model m WHERE m.provider_id = p.id AND m.identifier = ?)',
                    [$name, $modelIdentifier, (int) $generatesImages, (int) $acceptsImages, self::TEMPERATURE, $maxTokens, $identifier, $modelIdentifier],
                );
            }

            if ($created) {
                foreach (['chat' => 'active_chat_model_id', 'image' => 'active_image_model_id'] as $kind => $column) {
                    if ($provider[$kind] === null) {
                        continue;
                    }
                    $this->addSql(
                        sprintf(
                            'UPDATE app_ai_provider SET %s = (SELECT m.id FROM app_ai_model m WHERE m.provider_id = app_ai_provider.id AND m.identifier = ? LIMIT 1) '
                            . 'WHERE identifier = ? AND api_key IS NULL AND %s IS NULL AND id = (SELECT t.id FROM (SELECT MIN(id) AS id FROM app_ai_provider WHERE identifier = ?) t)',
                            $column,
                            $column,
                        ),
                        [$provider[$kind], $identifier, $identifier],
                    );
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Nothing to undo: the rows may have been given keys and uses since, and an administrator can delete them in the admin.
    }
}
