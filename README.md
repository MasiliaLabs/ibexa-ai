# Ibexa AI

[![CI](https://github.com/MasiliaLabs/ibexa-ai/actions/workflows/ci.yml/badge.svg)](https://github.com/MasiliaLabs/ibexa-ai/actions/workflows/ci.yml)
[![Latest version](https://img.shields.io/packagist/v/masilia/ibexa-ai.svg)](https://packagist.org/packages/masilia/ibexa-ai)
[![PHP](https://img.shields.io/packagist/php-v/masilia/ibexa-ai.svg)](https://packagist.org/packages/masilia/ibexa-ai)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

The AI assistant for Ibexa 5: a registry of capabilities that mirror the admin, a chat for editors, an approval flow, and one AI provider per siteaccess.

`masilia/ibexa-ai` is part of a family of packages for [Ibexa](https://www.ibexa.co) that work together or alone. See [the other packages](#the-masilia-packages).

## What it is

An AI assistant for the Ibexa admin, built on [Neuron AI](https://neuron-ai.dev) (v4). Instead of a few hard-coded tools, it gives the model a **registry of capabilities** that mirror what the Ibexa admin does — with the same rules — so an editor can ask in plain language and approve each change.

## Features

- **Capabilities**: more than a hundred, grouped in domains (content, trash, structure, URLs, relations, users and roles…). Each is a Neuron tool, found by the model with `tool_search`, checked with Ibexa's own rules before it runs.
- **Risk tiers and approval**: every capability is `read`, `write`, `destructive` or `access`. Anything that changes something shows an **approval card** with a preview; `propose_changes` groups a batch. What the user's role does not allow is refused *before* the card.
- **Audit**: what the assistant did, for whom, and with what result.
- **A chat** in the admin (a floating widget built with [assistant-ui](https://www.assistant-ui.com) over the AG-UI protocol), for everyone whose role has `masilia_ai/use`. Conversations are kept and can be renamed or deleted.
- **Providers per siteaccess**: OpenAI, Anthropic, Gemini, Mistral, Ollama, Qwen, Kimi, MiniMax and MiMo, configured in the admin (**AI Assistant** page), one provider answering for a siteaccess. API keys are **encrypted at rest**. A fallback `connection` can be set in configuration.
- **Two admin pages**: **AI Assistant** (`/ai/settings`: engine status, providers and models, usage, siteaccesses) and **What the assistant can do** (`/ai/capabilities`: every capability, its risk, what it needs, and whether you can use it).
- **Extensible**: other packages add capabilities, field inputs and skills through the contracts in `Masilia\Contracts\IbexaAi` (`masilia.ibexa_ai.capability` tag). [`masilia/ibexa-ai-craft`](https://github.com/MasiliaLabs/ibexa-ai-craft) and [`masilia/ibexa-ai-editor`](https://github.com/MasiliaLabs/ibexa-ai-editor) are built that way.

## Requirements

- PHP 8.3 or later
- Ibexa 5.0 (Symfony 7.4)
- Node.js 24 and Yarn, to build the admin assets with the Ibexa Encore setup of your project

Installed with it by Composer:

- [`masilia/ibexa-admin-kit`](https://github.com/MasiliaLabs/ibexa-admin-kit) for the React components of its admin pages

Works better with (optional):

- [`masilia/ibexa-ai-editor`](https://github.com/MasiliaLabs/ibexa-ai-editor) AI suggestions and images in the editing form
- [`masilia/ibexa-ai-craft`](https://github.com/MasiliaLabs/ibexa-ai-craft) building sites and pages out of blocks

## Installation

```bash
composer require masilia/ibexa-ai
```

1. Enable the bundles in `config/bundles.php`:

   ```php
   Masilia\Bundle\IbexaAdminKit\MasiliaIbexaAdminKitBundle::class => ['all' => true],
   Masilia\Bundle\IbexaAi\MasiliaIbexaAiBundle::class => ['all' => true],
   ```

2. Import the routes (`config/routes/masilia_ibexa_ai.yaml`):

   ```yaml
   masilia_ibexa_ai:
       resource: '@MasiliaIbexaAiBundle/Resources/config/routes.yaml'
   ```

3. Set an encryption key for the API keys and create the tables:

   ```bash
   php -r 'echo base64_encode(random_bytes(32));'     # put the result in AI_ENCRYPTION_KEY
   php bin/console doctrine:migrations:migrate
   ```

   ```yaml
   # config/packages/masilia_ibexa_ai.yaml
   masilia_ibexa_ai:
       encryption_key: '%env(AI_ENCRYPTION_KEY)%'
   ```

   Without an explicit key, one is derived from `APP_SECRET`: changing the secret would make the stored keys unreadable. If keys were stored before encryption existed, run `php bin/console masilia:ai:encrypt-keys` (safe to repeat, `--dry-run` shows what it would do).

4. Build the admin assets and give a role the policies:

   ```bash
   yarn ibexa-generate-tsconfig && yarn encore dev   # or: yarn encore production
   ```

   | Policy (`masilia_ai`) | Lets the role… |
   | --- | --- |
   | `use` | open the chat and use read capabilities |
   | `write` | approve changes to contents |
   | `destructive` | approve deletions and the like |
   | `structure` | change the content model (types, groups, fields) |
   | `access` | manage roles, policies and users (off by default) |

   The Administrator role has them all.

## Configuration

```yaml
masilia_ibexa_ai:
    # A single provider for every siteaccess, when you do not use the admin page
    connection:
        provider: openai          # openai, anthropic, gemini, mistral, ollama, qwen, kimi, minimax or mimo
        model: gpt-4o
        api_key: '%env(OPENAI_API_KEY)%'
        temperature: 0.7
        max_tokens: 4096
    os:
        domains:
            access: false         # roles, policies and users: off until you turn it on
    encryption_key: '%env(AI_ENCRYPTION_KEY)%'
```

## Usage

Open **AI Assistant** in the admin, add a provider (a key, an endpoint for local models), pick its chat model and assign it to a siteaccess. **Check again** tests the engine. Then open the chat from the floating button and ask, for example, *"list the children of the home page"* or *"create a folder called News under the home page"* — the second one shows an approval card.

Checks for developers and for your deployments:

```bash
php bin/console masilia:ai:os:coverage [--strict]    # admin routes that no capability covers
php bin/console masilia:ai:os:selftest <locationId>  # runs every capability on throwaway folders, then deletes them
```

## Tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=2G --no-progress
```

## AI agent skill

This package ships a skill for AI coding agents (Claude Code and other agents that read `SKILL.md` skills): how to install, configure, operate and troubleshoot `masilia/ibexa-ai`. It lives in [`skills/masilia-ibexa-ai`](skills/masilia-ibexa-ai/SKILL.md) and comes with the package.

```bash
# For the project (committed with it): from the installed package
mkdir -p .claude/skills && cp -r vendor/masilia/ibexa-ai/skills/masilia-ibexa-ai .claude/skills/

# For all your projects: global
mkdir -p ~/.claude/skills && cp -r vendor/masilia/ibexa-ai/skills/masilia-ibexa-ai ~/.claude/skills/
```

Restart the agent (or reload its skills) to pick it up. Update the copy after `composer update`.

## The Masilia packages

| Package | What it does |
| --- | --- |
| [`masilia/ibexa-admin-kit`](https://github.com/MasiliaLabs/ibexa-admin-kit) | React components in the design of the Ibexa admin, for admin pages |
| [`masilia/ibexa-sites`](https://github.com/MasiliaLabs/ibexa-sites) | A registry of sites that Ibexa serves without a deploy |
| [`masilia/ibexa-ai`](https://github.com/MasiliaLabs/ibexa-ai) | The AI assistant for Ibexa: capabilities, providers, chat, approval flow |
| [`masilia/ibexa-ai-craft`](https://github.com/MasiliaLabs/ibexa-ai-craft) | The assistant builds sites and pages out of blocks |
| [`masilia/ibexa-ai-editor`](https://github.com/MasiliaLabs/ibexa-ai-editor) | AI suggestions and image generation in the content editing form |
| [`masilia/ibexa-blocks`](https://github.com/MasiliaLabs/ibexa-blocks) | Front-office rendering of pages made of blocks, with themes |
| [`masilia/editorial-toolbox`](https://github.com/MasiliaLabs/editorial-toolbox) | In-context editing toolbar for the front office |

**What requires what** (Composer installs it for you):

- `ibexa-sites` requires `ibexa-admin-kit`
- `ibexa-ai` requires `ibexa-admin-kit`
- `ibexa-ai-editor` requires `ibexa-ai`
- `ibexa-ai-craft` requires `ibexa-ai`, `ibexa-blocks` and `ibexa-sites`
- `ibexa-blocks` and `editorial-toolbox` require none of the others; they are happy to find `ibexa-sites` (and each other) when installed.

## Compatibility

| Package version | PHP | Ibexa |
| --- | --- | --- |
| `latest` | 8.3+ | 5.0 |

## Download

- **Composer** (recommended): `composer require masilia/ibexa-ai` — [ibexa-ai on Packagist](https://packagist.org/packages/masilia/ibexa-ai)
- **Archive** of a release: [GitHub releases](https://github.com/MasiliaLabs/ibexa-ai/releases) (each release lists what changed)
- **Source**: `git clone https://github.com/MasiliaLabs/ibexa-ai.git`
- What changed between versions: [CHANGELOG.md](CHANGELOG.md)

## Support, contributing and security

- Questions and bug reports: [GitHub issues](https://github.com/MasiliaLabs/ibexa-ai/issues)
- Upgrading across a breaking change: [UPGRADE.md](UPGRADE.md)
- Pull requests are welcome, see [CONTRIBUTING.md](CONTRIBUTING.md)
- Security problems: please follow [SECURITY.md](SECURITY.md) and do not open a public issue

## License

Released under the [MIT license](LICENSE). Copyright © 2026 MasiliaLabs.

Ibexa itself is distributed under its own licenses (Ibexa OSS: GPL-2.0), which apply to the project that uses this package. This package is an independent project, not affiliated with or endorsed by Ibexa AS; "Ibexa" is a trademark of its owner.
