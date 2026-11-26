# Upgrade guide

What to change in your project when you move to a version that breaks something. The full list of changes is in the [CHANGELOG](CHANGELOG.md).

## From 2.x to 3.0

- **Requires `masilia/ibexa-admin-kit` `^0.2`** (it was `^0.1`). Update the two together: `composer update masilia/ibexa-ai masilia/ibexa-admin-kit`. A project that requires the kit itself has to raise its constraint.
- Rebuild the admin after updating (`php bin/console ibexa:encore:compile`), refresh the scripts' translations (`bazinga:js-translation:dump public/assets --merge-domains`) and hard-reload the page.
- The entries of the **Admin** menu are **AI Assistant settings** and **AI Assistant capabilities** (they were "AI Assistant" and "What the assistant can do"), and so are the titles of the two pages. Nothing to change unless a document or a test of yours names the old labels.
- Packages built on the OS must require `masilia/ibexa-ai` `^3.0` (or `^2.0 || ^3.0` if they work with both): the API they use is the same.

## From 1.x to 2.0

- **`content.trash` is the admin's trash and nothing more.** It no longer unlinks the contents that point at the one you trash. To remove those links too, give `relations.unlink` in the same `propose_changes`. If your own capabilities or skills relied on `content.trash` unlinking, add that step.
- **The assistant is generic.** The OS names no project in its examples, comments or tests, and a test keeps it so. Anything project specific (site structure, locations, conventions) belongs in a package built on it, such as [`masilia/ibexa-ai-craft`](https://github.com/MasiliaLabs/ibexa-ai-craft).
- **`NovaSeoMetasInput`** is registered only when Nova SEO is installed; keep `novactive/ezseobundle` if you fill the SEO metas.
- Packages built on the OS must require `masilia/ibexa-ai` `^2.0`.

## From 0.5 to 1.0

- **One writer of contents** replaces the two classes of the previous package (`masilia/ai-assistant`). Code that used the old classes must use `ContentWriter` from `Masilia\Contracts\IbexaAi`.
- Tools are capabilities: a tool named after a capability uses `__` for the dots (`content.update` is `content__update`).
