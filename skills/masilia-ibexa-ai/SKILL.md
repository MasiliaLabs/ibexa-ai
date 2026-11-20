---
name: masilia-ibexa-ai
description: Install, configure, operate and debug the AI assistant for the Ibexa 5 admin, masilia/ibexa-ai (registry of 100+ capabilities on Neuron AI, risk tiers with approval cards, audit, providers per siteaccess, chat widget, encrypted API keys). Use whenever the user mentions the AI assistant/chat in the Ibexa admin, AI providers or models (OpenAI, Anthropic, Gemini, Mistral, Ollama...), API keys, AI_ENCRYPTION_KEY, capabilities, approval cards, `masilia:ai:*` commands, "what the assistant can do", assistant errors in logs, or masilia/ibexa-ai, even if the package is not named.
---

# masilia/ibexa-ai

The "AI OS" of the Ibexa admin, built on Neuron AI v4. Instead of hard-coded tools it exposes a **registry of capabilities** that mirror what the admin does, with the same rules (`Ibexa\Rules`). Each capability is a Neuron tool (`content.update` is `content__update`), found by the model with `tool_search`. Tiers: `read`, `write`, `destructive`, `access`; anything that changes something shows an **approval card**; what the role does not allow is refused before the card. Chat access is administrators with `masilia_ai/use`.

## Install
`composer require masilia/ibexa-ai`; enable `MasiliaIbexaAdminKitBundle` and `MasiliaIbexaAiBundle`; import `@MasiliaIbexaAiBundle/Resources/config/routes.yaml`; generate `AI_ENCRYPTION_KEY` (`php -r 'echo base64_encode(random_bytes(32));'`) and set `masilia_ibexa_ai.encryption_key: '%env(AI_ENCRYPTION_KEY)%'`; `doctrine:migrations:migrate`; `yarn add @ag-ui/client @assistant-ui/react @assistant-ui/react-ag-ui @assistant-ui/react-markdown remark-gfm` (run it **before** `composer require` so that the post-update build passes; Composer does not install them; without them the build fails with "contains a reference to the file" errors), then `yarn ibexa-generate-tsconfig && php bin/console ibexa:encore:compile` (`yarn encore dev --config-name ibexa` does not work: "Configuration ibexa was not found"); give roles the policies `masilia_ai` `use`, `write`, `destructive`, `structure`, `access` (off by default; Administrator has all). Without an explicit key one is derived from `APP_SECRET`: **changing APP_SECRET makes stored keys unreadable** (re-enter them). Keys stored before encryption: `masilia:ai:encrypt-keys [--dry-run]`.

With the Masilia recipes endpoint in the `composer.json` (`https://api.github.com/repos/MasiliaLabs/recipes/contents/index.json?ref=flex/main` in `extra.symfony.endpoint`, after Ibexa's), `composer require` already enables the bundle and writes the route and config files (see the README, section *With Symfony Flex*); then do what is left. Without the endpoint, do everything by hand.

## Operate
The migrations also install the providers and their current models (as of 2026-11-20: `Version20261120000000`; it only adds what is missing and never changes a row that exists): a new project only needs an API key in **AI Assistant**. Mistral and Ollama come without models: add the ones you use.
- Admin > **AI Assistant** (`/ai/settings`): add a provider (key; endpoint for local models), choose its chat (and image) model, assign it to a siteaccess, "Check again" tests the engine. One provider answers per siteaccess; `masilia_ibexa_ai.connection` is the fallback.
- Admin > **What the assistant can do** (`/ai/capabilities`): every capability, its risk, what it needs, whether the user can use it. Domain `access` (roles/users) is off until `os.domains.access: true`.
- Checks: `masilia:ai:os:coverage [--strict]` lists admin routes no capability covers (0 unmapped is the bar); `masilia:ai:os:selftest <parentLocationId>` runs every capability on throwaway folders and deletes them: it must end "All steps passed; the throwaway folders are gone". Run both after changing capabilities.

## Add a capability
Implement the contract in `Masilia\Contracts\IbexaAi` and tag it `masilia.ibexa_ai.capability` (or use the craft/editor packages as models). Keep the description and the input schema precise: the model chooses by them. Reuse the Ibexa rules, never bypass permissions. Return problems as sentences the model can act on.

## Troubleshooting
- Provider keys unreadable: `APP_SECRET` changed or `AI_ENCRYPTION_KEY` differs from the one used to store them.
- Chat button shows old UI after a release: the chat chunk is lazy and hashed; one hard reload.
- Assistant refuses an action: read the role policies (`masilia_ai/write` etc.); the refusal text names what is missing.
- Look at the assistant log of operations for validation errors; they are detailed per field.
- After SQL changes or restores, clear `cache:clear` and `cache:pool:clear cache.redis cache.app`.
Never type the user's passwords or API keys on their behalf; they enter them in the admin.
