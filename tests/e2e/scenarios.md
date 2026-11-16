# End-to-end scenarios (browser)

Run on the admin host (https://admin.example.test) as an administrator, with a chat provider active. Sandbox parent: a throwaway location you can write under.
Everything created is named "E2E <time>" and removed at the end. Record for each scenario: OK/KO + evidence (screenshot, request, content id).

A. Admin pages: menu entries; /ai/settings loads providers (`…/api/data` 200); /ai/capabilities lists 110 capabilities, filter and "only what I can use" work.
B. Chat, read: "list the children of <sandbox location id>" runs without a card; reload keeps the conversation (`agui/state`); rename (PATCH) and delete (DELETE) a conversation carry `X-CSRF-Token`, 200.
C. Chat, write: create a folder -> approval card (write, preview); refuse with a reason -> agent corrects; approve -> folder in the tree; trash (card) -> in trash; restore; trash again; purge only E2E items.
D. Batch: folder + sub-folder -> ONE `propose_changes` card; a batch with an unknown type creates nothing.
E. Field values: create a content with date, URL, number, relation and Markdown description -> values correct in the Ibexa edit form, description formatted (no `**`).
F. Attachment and mention: attach an image -> `media.add_image` card -> image in the media library; `@` mention -> `mentions` request OK.
G. Access: no session on /api/ai/agent/threads -> login redirect; write without CSRF header (console) -> 403.
H. craft: create a page with two blocks, move and remove one, `site.conventions`; public page 200; clean up.
I. editor: edit form shows AI buttons (`field-types` 200); streamed suggestion, cancel, accept without publishing; languages list; (optional, costs) image generation.
J. Journal: "Latest activity" on /ai/capabilities shows B-H; var/log/ai.log has no unexpected error.
