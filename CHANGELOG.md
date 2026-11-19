# Changelog

All notable changes to `masilia/ibexa-ai` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/) (before 1.0, a minor version may contain breaking changes).

## [Unreleased]

### Fixed
- `masilia:ai:os:selftest` no longer fails on a blank project: the type is named in a second language only when the repository has one (it assumed `fre-FR`), and "what is under B was not copied" ignores the copy itself (the subtree of a location contains the location), so it only fails when a real child was copied.

### Changed
- README and agent skill: the npm packages the chat needs (`@ag-ui/client`, `@assistant-ui/react`, `@assistant-ui/react-ag-ui`, `@assistant-ui/react-markdown`, `remark-gfm`) are listed in the install steps, and the admin build is `php bin/console ibexa:encore:compile` (`yarn encore dev --config-name ibexa` does not exist).

## [2.3.3] - 2026-11-19

### Fixed
- A blank project can install the package: a migration now creates `app_ai_provider`, `app_ai_model`, `app_ai_provider_siteaccess` and `app_ai_request_log`, which no migration created before (the next migration failed with "Table app_ai_provider doesn't exist"). It is additive and idempotent: a database that already has the tables is left as it is.

## [2.3.2] - 2026-11-19

### Added
- An agent skill, `skills/masilia-ibexa-ai`, for AI coding agents that work with the package; the README says how to install it.

## [2.3.1] - 2026-11-19

### Added
- The summary of an earlier conversation (a message that starts with "## Previous conversation summary:") shows its Markdown rendered, in the bubble of the message, instead of raw text.

### Fixed
- `ibexa_author` fields (the sender and recipients of a form, the authors of an article) are filled: the assistant gives "Name <email>", an e-mail address or `{name, email}`; before, a plain text was refused by Ibexa and no form block could be created.
- A content that Ibexa refuses to create says which field and why (`Field "recipients" (eng-GB): …`), as the validation before the approval already did; the type creation names the field definitions it refused. The model can correct the field instead of repeating the call.
- The on-demand chunk of the chat has a content hash in its file name: a browser can no longer pair a cached older copy of it with the scripts of the page ("Cannot find module" error, a launcher that does not change after an update).

### Changed
- The launcher of the chat is a chatty speech bubble (SVG, outlined, in the primary colour of the admin): its three dots keep typing, a spark badge pulses, and the label "Ask the assistant" is typed above it once when the page opens, then fades. The animated robot (`AssistantMark.tsx`) is kept as another look. Motion follows the reduced-motion preference.

## [2.3.0] - 2026-11-17

### Added
- The engine refresh button of the AI Assistant page turns while the status is checked.
- The active chat and image models are shown in the notice of the AI engine header.

### Changed
- Requires `masilia/ibexa-admin-kit` `^0.1.1`, and declares the dependencies the code uses directly.
- Examples and test fixtures are neutral (no project names).

### Fixed
- No rule between the AI engine header and the tabs.

## [2.2.0] - 2026-11-15

### Added
- A siteaccess is answered by one provider: assigning it takes it from the others.
- The **What the assistant can do** page and the **AI Assistant** page are rebuilt on `masilia/ibexa-admin-kit` (tabs, filter bar, sections, tables, dialogs); siteaccesses are chosen in a multiple dropdown.

### Fixed
- The provider of a siteaccess is chosen by name, as the settings page shows it.
- An image API refusal says why.

## [2.1.0] - 2026-11-14

### Added
- The chat is for whoever the role lets use the assistant (`masilia_ai/use`), not only administrators; its button only shows for them.
- What the role does not allow (create under a parent, edit or trash a content) is refused before the approval card, not after.

### Fixed
- `ChatAccessConfig` keeps its admin config tag.

## [2.0.0] - 2026-11-14

### Added
- The admin pages and menu are translated (domain `masilia_ibexa_ai`, English and French).

### Changed
- **Breaking:** `content.trash` is the admin's trash and nothing more; unlinking the contents that point at it is the explicit `relations.unlink`.
- The OS is generic: no project identifier in its examples, comments or tests, and a test that keeps it so.
- `NovaSeoMetasInput` keeps the content of a meta as given and is only registered when Nova SEO is installed.

## [1.0.0] - 2026-11-14

The AI OS: a registry of capabilities that mirror the Ibexa admin.

### Added
- The rest of the admin's editing behaviour: drafts, translations from a language, users, draft conflicts, trash warnings.
- Each field says what value to give, and the prompt's list of formats comes from the same table.

### Changed
- **Breaking:** one writer of contents, in place of the two classes of the previous package.

### Fixed
- The call budget: a refusal the model can read, not an exception that ends the turn.

## [0.5.0] - 2026-11-13

### Added
- The admin page **What the assistant can do**.
- Access capabilities: roles, policies, assignments and users (off by default).
- Packages built on the OS can add steps to `masilia:ai:os:selftest`.

### Changed
- The OS no longer knows how a site is built: extension points for the packages built on it.

### Fixed
- A list that a model wraps in an object is still the list.

## [0.4.3] - 2026-11-12

### Changed
- Ibexa value objects are read through their accessors; three latent bugs fixed.

## [0.4.2] - 2026-11-12

### Fixed
- The fallback stringifier is registered; silent drops are guarded against.

## [0.4.1] - 2026-11-12

### Fixed
- Field settings are read through `getFieldSettings()`.

## [0.4.0] - 2026-11-12

### Added
- Provider API keys are encrypted at rest (`masilia:ai:encrypt-keys`, `AI_ENCRYPTION_KEY`).

## [0.3.0] - 2026-11-12

### Changed
- The settings and usage screens are rebuilt in TypeScript and React.

## [0.2.0] - 2026-11-12

### Added
- The suggestion modal is rebuilt in TypeScript and React with IDS components; suggestions are streamed as named, versioned events with keep-alive.

## [0.1.1] - 2026-11-12

### Changed
- MiMo is listed among the providers of the connection setting.

## [0.1.0] - 2026-11-12

First release: the assistant for Ibexa 5 on Neuron AI, with a provider layer, content suggestions, and an agent with an approval flow.

[Unreleased]: https://github.com/MasiliaLabs/ibexa-ai/compare/2.3.3...HEAD
[2.3.3]: https://github.com/MasiliaLabs/ibexa-ai/compare/2.3.2...2.3.3
[2.3.2]: https://github.com/MasiliaLabs/ibexa-ai/compare/2.3.1...2.3.2
[2.3.1]: https://github.com/MasiliaLabs/ibexa-ai/compare/2.3.0...2.3.1
[2.3.0]: https://github.com/MasiliaLabs/ibexa-ai/compare/2.2.0...2.3.0
[2.2.0]: https://github.com/MasiliaLabs/ibexa-ai/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/MasiliaLabs/ibexa-ai/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/MasiliaLabs/ibexa-ai/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/MasiliaLabs/ibexa-ai/compare/0.5.0...1.0.0
[0.5.0]: https://github.com/MasiliaLabs/ibexa-ai/compare/0.4.3...0.5.0
[0.4.3]: https://github.com/MasiliaLabs/ibexa-ai/compare/0.4.2...0.4.3
[0.4.2]: https://github.com/MasiliaLabs/ibexa-ai/compare/0.4.1...0.4.2
[0.4.1]: https://github.com/MasiliaLabs/ibexa-ai/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/MasiliaLabs/ibexa-ai/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/MasiliaLabs/ibexa-ai/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/MasiliaLabs/ibexa-ai/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/MasiliaLabs/ibexa-ai/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/MasiliaLabs/ibexa-ai/releases/tag/0.1.0
