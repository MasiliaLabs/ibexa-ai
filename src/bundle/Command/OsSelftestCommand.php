<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Command;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\Bundle\IbexaAi\Os\Execution\CapabilityRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'masilia:ai:os:selftest',
    description: 'Run capabilities end to end on throwaway folders created under a location, then delete them.',
)]
final class OsSelftestCommand extends Command
{
    public function __construct(
        private readonly CapabilityRunner $runner,
        private readonly Repository $repository,
        private readonly PermissionResolver $permissions,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('parent_location_id', InputArgument::REQUIRED, 'Where the throwaway folders are created. Nothing else is touched.')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Id of the user the capabilities run as', '14');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $parent = (int) $input->getArgument('parent_location_id');
        $this->permissions->setCurrentUserReference(new UserReference((int) $input->getOption('user')));

        $contents = [];
        $failed = 0;
        $answers = [];
        $expectedRefusals = 0;

        /**
         * Runs a capability the way the assistant would (checked, typed, executed) and checks its answer.
         *
         * @param array<string, mixed> $arguments
         * @param callable(array<string, mixed>): ?string $check Returns what is wrong with the answer data
         */
        $step = function (string $label, string $capability, array $arguments, ?callable $check = null, ?string $remember = null, bool $refused = false) use (&$contents, &$failed, &$answers, &$expectedRefusals, $io): void {
            [$cap, $typed, $problem] = $this->runner->prepare($capability, $arguments);
            if ($refused && $problem !== null) {
                $io->writeln(sprintf(' <info>ok</info>   %s (%s)', $label, $problem));

                return;
            }
            if ($cap === null || $problem !== null) {
                $io->writeln(sprintf(' <error>FAIL</error> %s: %s', $label, $problem ?? 'unknown capability'));
                ++$failed;

                return;
            }
            $result = $this->runner->run($cap, $typed, false);
            if ($refused) {
                ++$expectedRefusals;
                $io->writeln($result->success ? ' <error>FAIL</error> ' . $label . ': it was allowed' : sprintf(' <info>ok</info>   %s (%s)', $label, $result->message));
                $failed += $result->success ? 1 : 0;

                return;
            }
            $wrong = $result->success ? ($check !== null ? $check($result->data) : null) : $result->message;
            if (isset($result->data['content_id']) && $result->success && in_array($capability, ['content.create_folder', 'location.copy'], true)) {
                $contents[] = (int) $result->data['content_id'];
            }
            if ($remember !== null) {
                $answers[$remember] = $result->data;
            }
            if ($wrong === null) {
                $io->writeln(sprintf(' <info>ok</info>   %s', $label));

                return;
            }
            ++$failed;
            $io->writeln(sprintf(' <error>FAIL</error> %s: %s', $label, $wrong));
        };

        $stamp = date('His');
        try {
            $io->title('AI OS selftest');
            $step('create folder A', 'content.create_folder', ['name' => "Selftest A $stamp", 'parent_location_id' => $parent], null, 'a');
            $step('create folder B', 'content.create_folder', ['name' => "Selftest B $stamp", 'parent_location_id' => $parent], null, 'b');
            $a = $answers['a'] ?? [];
            $b = $answers['b'] ?? [];
            if (!isset($a['location_id'], $b['location_id'])) {
                $io->error('The folders were not created: nothing more to test.');

                return Command::FAILURE;
            }

            $step('A also appears under B', 'location.add', ['content_id' => $a['content_id'], 'parent_location_id' => $b['location_id']], null, 'added');
            $step('A has two locations', 'location.list', ['content_id' => $a['content_id']], static fn (array $d): ?string => count($d['locations']) === 2 ? null : sprintf('expected 2 locations, got %d', count($d['locations'])));
            $step('hide A', 'location.set_visibility', ['location_id' => $a['location_id'], 'visible' => false]);
            $step('A is hidden', 'location.list', ['content_id' => $a['content_id']], static fn (array $d): ?string => array_filter($d['locations'], static fn (array $l): bool => $l['location_id'] === $a['location_id'] && $l['hidden']) !== [] ? null : 'A is not hidden');
            $step('show A', 'location.set_visibility', ['location_id' => $a['location_id'], 'visible' => true]);
            $step('priority of A', 'location.update', ['location_id' => $a['location_id'], 'priority' => 7]);
            $step('the extra location is not the main one', 'location.list', ['content_id' => $a['content_id']], static fn (array $d): ?string => array_filter($d['locations'], static fn (array $l): bool => $l['location_id'] === $a['location_id'] && $l['is_main']) !== [] ? null : 'the first location is not main');
            $step('A refers to nothing', 'relations.list', ['content_id' => $a['content_id']], static fn (array $d): ?string => $d['incoming_total'] === 0 ? null : 'unexpected incoming relation');
            $step('copy B below the parent', 'location.copy', ['location_id' => $b['location_id'], 'target_parent_location_id' => $parent], null, 'copy');
            // Copying B copied what is under it too (A, shown there): those copies are ours to delete.
            array_push($contents, ...$this->subtreeContentIds((int) ($answers['copy']['location_id'] ?? 0)));
            $step('swap A and B', 'location.swap', ['location_id' => $a['location_id'], 'other_location_id' => $b['location_id']]);
            $step('move the copy under B', 'location.move', ['location_id' => $answers['copy']['location_id'] ?? 0, 'new_parent_location_id' => $b['location_id']]);
            $step('move B under itself is refused', 'location.move', ['location_id' => $b['location_id'], 'new_parent_location_id' => $answers['copy']['location_id'] ?? 0], null, null, true);
            $step('remove the extra location of A', 'location.remove', ['location_id' => $answers['added']['location_id'] ?? 0]);
            $this->domains($step, $answers, $a['content_id'], $stamp);
            $this->structure($step, $answers, $contents, $parent, $stamp);
            $step('trash A', 'content.trash', ['content_id' => $a['content_id']]);
            $step('delete A from the trash', 'trash.delete', ['content_id' => $a['content_id']]);
            $step('it is no longer in the trash', 'trash.delete', ['content_id' => $a['content_id']], null, null, true);
        } finally {
            // What was trashed (A, and what sat under it) is deleted from the trash first, then the rest for good.
            foreach ($this->trashLeftovers($contents) as $leftover) {
                $io->warning($leftover);
            }
            $content = $this->repository->getContentService();
            foreach (array_unique($contents) as $id) {
                try {
                    $content->deleteContent($content->loadContentInfo($id));
                } catch (Throwable) {
                    // Already gone (deleted with its parent).
                }
            }
            $this->removeStructures($stamp);
        }

        if ($failed > 0) {
            $io->error(sprintf('%d step(s) failed.', $failed));

            return Command::FAILURE;
        }
        $io->success('All steps passed; the throwaway folders are gone.');

        return Command::SUCCESS;
    }

    private function sectionIdentifier(string $stamp): string
    {
        return 'selftest_' . $stamp;
    }

    /**
     * Sections, states, languages and the user's own spaces, on the content A.
     *
     * @param callable $step
     * @param array<string, array<string, mixed>> $answers
     */
    private function domains(callable $step, array &$answers, int|string $contentId, string $stamp): void
    {
        $section = $this->sectionIdentifier($stamp);
        $step('create a section', 'section.create', ['identifier' => $section, 'name' => 'Selftest ' . $stamp], null, 'section');
        $sectionId = (int) ($answers['section']['section_id'] ?? 0);
        $step('rename the section', 'section.update', ['section_id' => $sectionId, 'name' => 'Selftest renamed ' . $stamp]);
        $step('put A in the section', 'section.assign', ['section_id' => $sectionId, 'content_id' => $contentId]);
        $step('the section holds A', 'section.list', [], static fn (array $d): ?string => array_filter($d['sections'], static fn (array $s): bool => $s['section_id'] === $sectionId && $s['contents'] === 1) !== [] ? null : 'the section does not hold exactly A', 'sections');
        $step('a used section cannot be deleted', 'section.delete', ['section_id' => $sectionId], null, null, true);
        $standard = 0;
        foreach ($answers['sections']['sections'] ?? [] as $row) {
            if ($row['identifier'] === 'standard') {
                $standard = $row['section_id'];
            }
        }
        $step('put A back in the standard section', 'section.assign', ['section_id' => $standard, 'content_id' => $contentId]);
        $step('delete the section', 'section.delete', ['section_id' => $sectionId]);

        $step('create a group of states', 'state.group.create', ['identifier' => $section, 'name' => 'Selftest']);
        $step('create the default state', 'state.create', ['group' => $section, 'identifier' => 'draft_state', 'name' => 'Draft']);
        $step('create a second state', 'state.create', ['group' => $section, 'identifier' => 'done_state', 'name' => 'Done']);
        $step('rename the second state', 'state.update', ['group' => $section, 'state' => 'done_state', 'name' => 'Finished']);
        $step('set A to the second state', 'state.set', ['content_id' => $contentId, 'group' => $section, 'state' => 'done_state']);
        $step('the second state holds A', 'state.list', [], static function (array $d) use ($section): ?string {
            foreach ($d['groups'] as $group) {
                foreach ($group['identifier'] === $section ? $group['states'] : [] as $state) {
                    if ($state['identifier'] === 'done_state') {
                        return $state['contents'] === 1 && $state['name'] === 'Finished' ? null : sprintf('%d content(s), name "%s"', $state['contents'], $state['name']);
                    }
                }
            }

            return 'the group is not listed';
        });
        $step('delete the second state', 'state.delete', ['group' => $section, 'state' => 'done_state']);
        $step('delete the group', 'state.group.delete', ['group' => $section]);

        $step('add a language', 'localization.language.create', ['language_code' => 'tst-ZZ', 'name' => 'Selftest language']);
        $step('translate A into it', 'localization.translation.add', ['content_id' => $contentId, 'language_code' => 'tst-ZZ', 'fields' => ['name' => 'Selftest translated']]);
        $step('A cannot be translated twice into it', 'localization.translation.add', ['content_id' => $contentId, 'language_code' => 'tst-ZZ', 'fields' => ['name' => 'Again']], null, null, true);
        $step('an empty translation is refused', 'localization.translation.add', ['content_id' => $contentId, 'language_code' => 'tst-ZZ', 'fields' => new \stdClass()], null, null, true);
        $step('remove the translation', 'localization.translation.remove', ['content_id' => $contentId, 'language_code' => 'tst-ZZ']);
        $step('disable the language', 'localization.language.update', ['language_code' => 'tst-ZZ', 'enabled' => false]);
        $step('a disabled language takes no translation', 'localization.translation.add', ['content_id' => $contentId, 'language_code' => 'tst-ZZ', 'fields' => ['name' => 'No']], null, null, true);
        $step('it is listed, disabled', 'localization.language.list', [], static fn (array $d): ?string => array_filter($d['languages'], static fn (array $l): bool => $l['language_code'] === 'tst-ZZ' && !$l['enabled']) !== [] ? null : 'not listed as disabled');
        $step('the default language cannot be deleted', 'localization.language.delete', ['language_code' => $this->repository->getContentLanguageService()->getDefaultLanguageCode()], null, null, true);
        $step('delete the language', 'localization.language.delete', ['language_code' => 'tst-ZZ']);
        $step('A has no translation to switch to', 'localization.set_main_language', ['content_id' => $contentId, 'language_code' => 'tst-ZZ'], null, null, true);
        $step('A is not translated into another language', 'localization.translation.remove', ['content_id' => $contentId, 'language_code' => $this->repository->getContentService()->loadContentInfo((int) $contentId)->mainLanguageCode], null, null, true);

        // Where A is now (the swap moved contents between locations).
        $step('where A is', 'location.list', ['content_id' => $contentId], null, 'where');
        $locationId = (int) ($answers['where']['locations'][0]['location_id'] ?? 0);
        $path = '/selftest-' . $stamp;
        $step('add a custom URL to A', 'url.alias.add', ['location_id' => $locationId, 'path' => $path, 'language_code' => 'eng-GB'], null, 'alias');
        $step('the same URL cannot be added twice', 'url.alias.add', ['location_id' => $locationId, 'path' => $path, 'language_code' => 'eng-GB'], null, null, true);
        $step('the URL is listed as custom', 'url.alias.list', ['location_id' => $locationId], static fn (array $d): ?string => array_filter($d['aliases'], static fn (array $a): bool => $a['custom'] && str_contains($a['path'], 'selftest-')) !== [] ? null : 'not listed');
        $step('a system alias cannot be removed', 'url.alias.remove', ['alias_id' => $this->systemAliasId($locationId)], null, null, true);
        $step('remove the custom URL', 'url.alias.remove', ['alias_id' => $answers['alias']['alias_id'] ?? 'none']);

        $step('create a wildcard', 'url.wildcard.create', ['source_url' => '/selftest-old-' . $stamp . '/*', 'destination_url' => '/selftest-new-' . $stamp . '/{1}', 'forward' => true], null, 'wildcard');
        $step('change the wildcard', 'url.wildcard.update', ['wildcard_id' => $answers['wildcard']['wildcard_id'] ?? 0, 'forward' => false]);
        $step('the wildcard is listed, as a rewrite', 'url.wildcard.list', [], static fn (array $d): ?string => array_filter($d['wildcards'], static fn (array $w): bool => str_contains($w['source_url'], 'selftest-old-') && !$w['forward']) !== [] ? null : 'not listed as a rewrite');
        $step('delete the wildcard', 'url.wildcard.delete', ['wildcard_id' => $answers['wildcard']['wildcard_id'] ?? 0]);
        $step('list the links', 'url.link.list', ['limit' => 5]);

        $step('the versions of A', 'content.version.load', ['content_id' => $contentId], static fn (array $d): ?string => count($d['versions']) >= 1 ? null : 'no version', 'versions');
        $published = 1;
        foreach ($answers['versions']['versions'] ?? [] as $version) {
            if ($version['status'] === 'published') {
                $published = $version['version_no'];
            }
        }
        $step('start a draft of A', 'content.draft.create', ['content_id' => $contentId], null, 'draft');
        $step('read that draft', 'content.version.load', ['content_id' => $contentId, 'version_no' => $answers['draft']['version_no'] ?? 1]);
        $step('a draft is not deleted as a version', 'content.version.delete', ['content_id' => $contentId, 'version_no' => $answers['draft']['version_no'] ?? 1], null, null, true);
        $step('the published version is not deleted', 'content.version.delete', ['content_id' => $contentId, 'version_no' => $published], null, null, true);
        $step('discard the draft', 'workspace.drafts.delete', ['content_id' => $contentId, 'version_no' => $answers['draft']['version_no'] ?? 1]);

        $step('list the bookmarks', 'workspace.bookmarks.list', []);
        $step('list the drafts', 'workspace.drafts.list', []);
        $step('list the notifications', 'workspace.notifications.list', ['limit' => 5]);
        $step('list the preferences', 'workspace.preferences.list', [], null, 'preferences');
        // Setting a preference to the value it has changes nothing, and tests the write.
        $first = $answers['preferences']['preferences'][0] ?? null;
        if ($first !== null) {
            $step('set a preference to its own value', 'workspace.preferences.set', ['name' => $first['name'], 'value' => (string) $first['value']]);
        }
    }

    /**
     * Content types: a group, a type with fields, its changes, a content of it, and their deletion.
     *
     * @param callable                             $step
     * @param array<string, array<string, mixed>>  $answers
     * @param list<int>                            $contents
     */
    private function structure(callable $step, array &$answers, array &$contents, int $parent, string $stamp): void
    {
        $group = 'selftest_' . $stamp;
        $type = 'selftest_type_' . $stamp;
        $fieldIds = static fn (array $d): array => array_column($d['fields'], 'identifier');

        $step('create a group of types', 'structure.group.create', ['identifier' => $group]);
        $step('the group is listed', 'structure.group.list', [], static fn (array $d): ?string => in_array($group, array_column($d['groups'], 'identifier'), true) ? null : 'not listed');
        $step('the field types are listed', 'structure.field_type.list', [], static fn (array $d): ?string => in_array('ibexa_string', $d['field_types'], true) ? null : 'ibexa_string is missing');
        $step('a type with an unknown field type is refused', 'structure.type.create', ['identifier' => $type, 'name' => 'Selftest', 'group' => $group, 'fields' => [['identifier' => 'title', 'field_type' => 'nope_nope']]], null, null, true);
        $step('create a type with two fields', 'structure.type.create', ['identifier' => $type, 'name' => 'Selftest type', 'group' => $group, 'container' => true, 'fields' => [
            ['identifier' => 'title', 'field_type' => 'ibexa_string', 'name' => 'Title', 'required' => true],
            ['identifier' => 'body', 'field_type' => 'ibexa_text', 'name' => 'Body'],
        ]]);
        $step('describe it', 'structure.type.describe', ['type' => $type], static fn (array $d): ?string => $fieldIds($d) === ['title', 'body'] && $d['name_schema'] === '<title>' && $d['container'] ? null : 'unexpected fields or schema: ' . implode(',', $fieldIds($d)));
        $step('add a field', 'structure.field.add', ['type' => $type, 'field' => ['identifier' => 'summary', 'field_type' => 'ibexa_text', 'name' => 'Summary']]);
        $step('the same field cannot be added twice', 'structure.field.add', ['type' => $type, 'field' => ['identifier' => 'summary', 'field_type' => 'ibexa_text']], null, null, true);
        $step('reorder the fields', 'structure.field.reorder', ['type' => $type, 'order' => ['body']]);
        $step('the order changed', 'structure.type.describe', ['type' => $type], static fn (array $d): ?string => $fieldIds($d) === ['body', 'title', 'summary'] ? null : 'order is ' . implode(',', $fieldIds($d)));
        $step('remove a field', 'structure.field.remove', ['type' => $type, 'field' => 'summary']);
        $step('a missing field cannot be removed', 'structure.field.remove', ['type' => $type, 'field' => 'summary'], null, null, true);
        $step('rename the type', 'structure.type.update', ['type' => $type, 'name' => 'Selftest renamed']);
        $step('name it in French', 'structure.type.translate', ['type' => $type, 'language_code' => 'fre-FR', 'name' => 'Type de test']);
        $step('the type is listed in its group', 'structure.type.list', ['group' => $group], static fn (array $d): ?string => count($d['types']) === 1 && $d['types'][0]['fields'] === ['body:ibexa_text', 'title:ibexa_string'] ? null : 'unexpected list');
        $step('a non-empty group cannot be deleted', 'structure.group.delete', ['group' => $group], null, null, true);

        $contents[] = $this->createContentOfType($type, $parent, 'Selftest content ' . $stamp);
        $step('a used type cannot be deleted', 'structure.type.delete', ['type' => $type], null, null, true);
        $step('copy the type', 'structure.type.copy', ['type' => $type], null, 'typecopy');
        $step('delete the copy', 'structure.type.delete', ['type' => $answers['typecopy']['type'] ?? 'none']);
        $content = $this->repository->getContentService();
        $content->deleteContent($content->loadContentInfo($contents[array_key_last($contents)]));
        $step('delete the type', 'structure.type.delete', ['type' => $type]);
        $step('delete the group', 'structure.group.delete', ['group' => $group]);
    }

    /** A published content of the type, written directly (the capabilities of contents are tested elsewhere). */
    private function createContentOfType(string $type, int $parent, string $title): int
    {
        $content = $this->repository->getContentService();
        $struct = $content->newContentCreateStruct($this->repository->getContentTypeService()->loadContentTypeByIdentifier($type), 'eng-GB');
        $struct->setField('title', $title);
        $draft = $content->createContent($struct, [$this->repository->getLocationService()->newLocationCreateStruct($parent)]);

        return $content->publishVersion($draft->getVersionInfo())->contentInfo->id;
    }

    /** The id of a system-generated alias of the location (those cannot be removed). */
    private function systemAliasId(int $locationId): string
    {
        $aliases = $this->repository->getURLAliasService();
        foreach ($aliases->listLocationAliases($this->repository->getLocationService()->loadLocation($locationId), false) as $alias) {
            return $alias->id;
        }

        return 'none';
    }

    /** What the domains step made, removed whatever happened. */
    private function removeStructures(string $stamp): void
    {
        $identifier = $this->sectionIdentifier($stamp);
        $types = $this->repository->getContentTypeService();
        $attempts = [
            function () use ($types, $identifier, $stamp): void {
                // The type, and the copies of it that a failed run may have left.
                foreach ($types->loadContentTypes($types->loadContentTypeGroupByIdentifier($identifier)) as $type) {
                    $types->deleteContentType($type);
                }
                $types->deleteContentTypeGroup($types->loadContentTypeGroupByIdentifier($identifier));
            },
            function () use ($types, $stamp): void {
                $copy = $types->loadContentTypeByIdentifier('copy_of_selftest_type_' . $stamp);
                $types->deleteContentType($copy);
            },
            fn () => $this->repository->getSectionService()->deleteSection($this->repository->getSectionService()->loadSectionByIdentifier($identifier)),
            fn () => $this->repository->getObjectStateService()->deleteObjectStateGroup($this->repository->getObjectStateService()->loadObjectStateGroupByIdentifier($identifier)),
            fn () => $this->repository->getContentLanguageService()->deleteLanguage($this->repository->getContentLanguageService()->loadLanguage('tst-ZZ')),
        ];
        $wildcards = $this->repository->getURLWildcardService();
        foreach ($wildcards->loadAll(0, 500) as $wildcard) {
            if (str_starts_with($wildcard->sourceUrl, '/selftest-old-' . $stamp)) {
                $attempts[] = fn () => $wildcards->remove($wildcard);
            }
        }
        foreach ($attempts as $attempt) {
            try {
                $attempt();
            } catch (Throwable) {
                // Already removed by the steps.
            }
        }
    }

    /**
     * The contents at a location and everything under it.
     *
     * @return list<int>
     */
    private function subtreeContentIds(int $locationId): array
    {
        if ($locationId === 0) {
            return [];
        }
        $locations = $this->repository->getLocationService();
        $ids = [];
        $walk = static function (\Ibexa\Contracts\Core\Repository\Values\Content\Location $location) use (&$walk, &$ids, $locations): void {
            $ids[] = $location->contentInfo->id;
            foreach ($locations->loadLocationChildren($location, 0, 100)->locations as $child) {
                $walk($child);
            }
        };
        $walk($locations->loadLocation($locationId));

        return $ids;
    }

    /**
     * What the selftest trashed (it goes to the trash, not to nothing) is deleted from it, by content.
     *
     * @param list<int> $contentIds
     *
     * @return list<string> What could not be deleted
     */
    private function trashLeftovers(array $contentIds): array
    {
        $problems = [];
        $trash = $this->repository->getTrashService();
        $query = new \Ibexa\Contracts\Core\Repository\Values\Content\Query(['limit' => 200]);
        try {
            $items = $trash->findTrashItems($query)->items;
        } catch (Throwable $e) {
            // A row of the trash whose content is gone makes the listing fail: the rest is still cleaned.
            return [sprintf('The trash could not be listed: %s', $e->getMessage())];
        }
        foreach ($items as $item) {
            if (in_array($item->contentInfo->id, $contentIds, true)) {
                try {
                    $trash->deleteTrashItem($item);
                } catch (Throwable $e) {
                    $problems[] = sprintf('Content %d is still in the trash: %s', $item->contentInfo->id, $e->getMessage());
                }
            }
        }

        return $problems;
    }
}
