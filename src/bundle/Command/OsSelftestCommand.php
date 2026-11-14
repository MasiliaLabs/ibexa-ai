<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Command;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\IbexaAi\Os\Execution\CapabilityRunner;
use Masilia\Contracts\IbexaAi\Os\Selftest\SelftestContext;
use Masilia\Contracts\IbexaAi\Os\Selftest\SelftestScenario;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
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
        /** @var iterable<SelftestScenario> The steps that packages on the OS add for their own capabilities */
        #[AutowireIterator('masilia.ibexa_ai.selftest')]
        private readonly iterable $scenarios = [],
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
            if (isset($result->data['content_id']) && $result->success && in_array($capability, ['content.create', 'location.copy', 'location.copy_subtree'], true)) {
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
            $step('create folder A', 'content.create', ['content_type' => 'folder', 'parent_location_id' => $parent, 'fields' => ['name' => "Selftest A $stamp"]], null, 'a');
            $step('create folder B', 'content.create', ['content_type' => 'folder', 'parent_location_id' => $parent, 'fields' => ['name' => "Selftest B $stamp"]], null, 'b');
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
            // The three reads. Loading is read from the repository, so it is checked exactly; search and the tree come from the
            // search index, which can lag a few seconds behind a write, so they are checked for their shape only.
            $step('load A by its id', 'content.load', ['content_id' => $a['content_id']], static fn (array $d): ?string => ($d['content_id'] ?? null) === $a['content_id'] && ($d['name'] ?? null) === "Selftest A $stamp" && is_array($d['fields'] ?? null) ? null : 'A is not loaded as it was created');
            $step('search answers with its results', 'content.search', ['name' => "Selftest A $stamp", 'limit' => 5], static fn (array $d): ?string => is_int($d['count'] ?? null) && is_array($d['results'] ?? null) ? null : 'no results list');
            $step('the tree of the parent is read', 'tree.browse', ['location_id' => $parent, 'limit' => 5], static fn (array $d): ?string => is_int($d['total'] ?? null) && is_array($d['items'] ?? null) ? null : 'no items list');
            $step('A refers to nothing', 'relations.list', ['content_id' => $a['content_id']], static fn (array $d): ?string => $d['incoming_total'] === 0 ? null : 'unexpected incoming relation');
            $step('copy B below the parent', 'location.copy', ['location_id' => $b['location_id'], 'target_parent_location_id' => $parent], null, 'copy');
            // The admin's "copy" is one content: what is under B is not copied.
            $step('what is under B was not copied', 'tree.browse', ['location_id' => $answers['copy']['location_id'] ?? 0], static fn (array $d): ?string => ($d['total'] ?? 0) === 0 ? null : 'the copy of B has children');
            $step('copy B and what is under it', 'location.copy_subtree', ['location_id' => $b['location_id'], 'target_parent_location_id' => $parent], null, 'subtree');
            // The copy of the subtree copied A as well (shown under B): those copies are ours to delete.
            array_push($contents, ...$this->subtreeContentIds((int) ($answers['subtree']['location_id'] ?? 0)));
            $step('a folder without its name is refused by Ibexa\'s validation, before anything is written', 'content.create', ['content_type' => 'folder', 'parent_location_id' => $a['location_id'], 'fields' => ['name' => ' ', 'short_name' => 'x']], null, null, true);
            $step('nothing holds A in a relation field: unlinking it changes nothing', 'relations.unlink', ['content_id' => $a['content_id']], static fn (array $d): ?string => ($d['unlinked_from'] ?? null) === [] ? null : 'something was unlinked');
            $step('a root cannot be moved', 'location.move', ['location_id' => 1, 'new_parent_location_id' => $parent], null, null, true);
            $step('a root cannot be copied with its subtree', 'location.copy_subtree', ['location_id' => 1, 'target_parent_location_id' => $parent], null, null, true);
            $step('hide A everywhere', 'content.set_visibility', ['content_id' => $a['content_id'], 'visible' => false]);
            $step('A is hidden in all its locations', 'location.list', ['content_id' => $a['content_id']], static fn (array $d): ?string => array_filter($d['locations'], static fn (array $l): bool => !$l['hidden'] && !$l['invisible']) === [] ? null : 'a location of A is still visible');
            $step('A cannot be hidden twice', 'content.set_visibility', ['content_id' => $a['content_id'], 'visible' => false], null, null, true);
            $step('show A everywhere', 'content.set_visibility', ['content_id' => $a['content_id'], 'visible' => true]);
            $step('there is no draft of A to publish yet', 'content.publish', ['content_id' => $a['content_id']], null, null, true);
            $step('start a draft of A', 'content.draft.create', ['content_id' => $a['content_id']], null, 'draft');
            $step('publish the draft of A', 'content.publish', ['content_id' => $a['content_id'], 'version_no' => $answers['draft']['version_no'] ?? 0], static fn (array $d): ?string => ($d['version_no'] ?? 0) === ($answers['draft']['version_no'] ?? -1) ? null : 'another version was published');
            $step('change A in a draft, not published', 'content.update', ['content_id' => $a['content_id'], 'fields' => ['name' => "Selftest A draft $stamp"], 'publish' => false], null, 'saved');
            $step('A is still published with its name', 'content.load', ['content_id' => $a['content_id']], static fn (array $d): ?string => ($d['name'] ?? null) === "Selftest A $stamp" ? null : 'the draft was published');
            $step('publish that draft', 'content.publish', ['content_id' => $a['content_id']], static fn (array $d): ?string => ($d['version_no'] ?? 0) > ($answers['draft']['version_no'] ?? 0) ? null : 'an older version was published');
            $step('A has its new name', 'content.load', ['content_id' => $a['content_id']], static fn (array $d): ?string => ($d['name'] ?? null) === "Selftest A draft $stamp" ? null : 'the name did not change');
            // A user is changed and removed with the user capabilities, not as a content (the admin has a user form for it).
            $step('the administrator is not changed as a content', 'content.update', ['content_id' => 14, 'fields' => ['first_name' => 'x']], null, null, true);
            $step('the administrator is not trashed as a content', 'content.trash', ['content_id' => 14], null, null, true);
            $step('swap A and B', 'location.swap', ['location_id' => $a['location_id'], 'other_location_id' => $b['location_id']]);
            $step('move the copy under B', 'location.move', ['location_id' => $answers['copy']['location_id'] ?? 0, 'new_parent_location_id' => $b['location_id']]);
            $step('move B under itself is refused', 'location.move', ['location_id' => $b['location_id'], 'new_parent_location_id' => $answers['copy']['location_id'] ?? 0], null, null, true);
            $step('remove the extra location of A', 'location.remove', ['location_id' => $answers['added']['location_id'] ?? 0]);
            $this->domains($step, $answers, $a['content_id'], $stamp);
            $this->structure($step, $answers, $contents, $parent, $stamp);
            $this->access($step, $answers, $stamp, $io);

            $context = new SelftestContext(
                \Closure::fromCallable($step),
                function () use (&$answers): array {
                    return $answers;
                },
                function (int $id) use (&$contents): void {
                    $contents[] = $id;
                },
                static function (string $reason) use ($io): void {
                    $io->writeln(sprintf(' <comment>skip</comment> %s', $reason));
                },
                $parent,
                $stamp,
            );
            foreach ($this->scenarios as $scenario) {
                $scenario->run($context);
            }
            $step('trash A', 'content.trash', ['content_id' => $a['content_id']]);
            $step('restore A from the trash', 'trash.restore', ['content_ids' => [$a['content_id']]], static fn (array $d): ?string => ($d['count'] ?? 0) === 1 ? null : 'A was not restored');
            $step('A has its location back after the restore', 'location.list', ['content_id' => $a['content_id']], static fn (array $d): ?string => ($d['locations'] ?? []) !== [] ? null : 'A has no location after the restore');
            $step('trash A again', 'content.trash', ['content_id' => $a['content_id']]);
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
            $this->removeAccess($stamp);
            foreach ($this->scenarios as $scenario) {
                try {
                    $scenario->cleanup();
                } catch (Throwable) {
                    // Whatever it could not remove is reported by its own checks next time.
                }
            }
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
        $step('a state that holds a content cannot be deleted', 'state.delete', ['group' => $section, 'state' => 'done_state'], null, null, true);
        $step('set A back to the first state', 'state.set', ['content_id' => $contentId, 'group' => $section, 'state' => 'draft_state']);
        $step('delete the second state', 'state.delete', ['group' => $section, 'state' => 'done_state']);
        $step('delete the group', 'state.group.delete', ['group' => $section]);

        $step('add a language', 'localization.language.create', ['language_code' => 'tst-ZZ', 'name' => 'Selftest language']);
        $step('a translation cannot start from a language the content lacks', 'localization.translation.add', ['content_id' => $contentId, 'language_code' => 'tst-ZZ', 'base_language_code' => 'ara-MA'], null, null, true);
        $step('translate A into it, starting from its own language', 'localization.translation.add', ['content_id' => $contentId, 'language_code' => 'tst-ZZ', 'base_language_code' => 'eng-GB', 'fields' => ['name' => 'Selftest translated']]);
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
     * Roles, policies, assignments and users, on a throwaway user group. Skipped when the access domain is turned off
     * (it is off by default): turn it on with masilia_ibexa_ai.os.domains.access to test it.
     *
     * @param callable                            $step
     * @param array<string, array<string, mixed>> $answers
     */
    private function access(callable $step, array &$answers, string $stamp, SymfonyStyle $io): void
    {
        [, , $off] = $this->runner->prepare('access.role.list', []);
        if ($off !== null && str_contains($off, 'turned off')) {
            $io->writeln(' <comment>skip</comment> access capabilities (the domain is turned off)');

            return;
        }

        $role = 'selftest_role_' . $stamp;
        $renamed = $role . '_renamed';
        $copy = $role . '_copy';
        $login = 'selftest_' . $stamp;
        $users = $this->repository->getUserService();

        // The throwaway group the role is assigned to and the user goes in (users and groups are made directly: the assistant
        // makes users, not groups).
        $groupStruct = $users->newUserGroupCreateStruct('eng-GB');
        $groupStruct->setField('name', 'Selftest group ' . $stamp);
        $group = $users->createUserGroup($groupStruct, $users->loadUserGroup(4));
        $groupId = $group->id;

        $limits = ['Section' => [1]];
        $step('create a role', 'access.role.create', ['name' => $role, 'policies' => [['module' => 'content', 'function' => 'read'], ['module' => 'content', 'function' => 'create', 'limitations' => $limits]]]);
        $step('a role with that name exists', 'access.role.create', ['name' => $role], null, null, true);
        $step('an unknown policy is refused', 'access.role.create', ['name' => $role . '_x', 'policies' => [['module' => 'nope', 'function' => 'nope']]], null, null, true);
        $step('the role is listed', 'access.role.list', [], static fn (array $d): ?string => in_array($role, array_column($d['roles'], 'name'), true) ? null : 'not listed');
        $step('describe the role', 'access.role.describe', ['role' => $role], static fn (array $d): ?string => count($d['policies']) === 2 && array_filter($d['policies'], static fn (array $p): bool => $p['function'] === 'create' && $p['limitations'] === ['Section' => [1]]) !== [] ? null : 'unexpected policies', 'described');
        $createId = 0;
        foreach ($answers['described']['policies'] ?? [] as $policy) {
            if ($policy['function'] === 'create') {
                $createId = $policy['policy_id'];
            }
        }
        $step('add a policy', 'access.policy.add', ['role' => $role, 'policy' => ['module' => 'content', 'function' => 'edit', 'limitations' => $limits]]);
        // Publishing a role gives its policies new ids: read them again.
        $step('describe it again', 'access.role.describe', ['role' => $role], null, 'described');
        foreach ($answers['described']['policies'] ?? [] as $policy) {
            if ($policy['function'] === 'create') {
                $createId = $policy['policy_id'];
            }
        }
        $step('widen a policy', 'access.policy.update', ['role' => $role, 'policy_id' => $createId, 'limitations' => ['Section' => [1, 2]]]);
        $step('the policies are as set', 'access.role.describe', ['role' => $role], static fn (array $d): ?string => count($d['policies']) === 3 && array_filter($d['policies'], static fn (array $p): bool => $p['function'] === 'create' && $p['limitations'] === ['Section' => [1, 2]]) !== [] ? null : 'unexpected policies: ' . json_encode($d['policies']), 'described');
        $editId = 0;
        foreach ($answers['described']['policies'] ?? [] as $policy) {
            if ($policy['function'] === 'edit') {
                $editId = $policy['policy_id'];
            }
        }
        $step('a policy of another role is refused', 'access.policy.remove', ['role' => $role, 'policy_id' => 999999], null, null, true);
        $step('remove a policy', 'access.policy.remove', ['role' => $role, 'policy_id' => $editId]);
        $step('rename the role', 'access.role.update', ['role' => $role, 'new_name' => $renamed]);
        $step('copy the role', 'access.role.copy', ['role' => $renamed, 'new_name' => $copy]);
        $step('assign the role to the group', 'access.assignment.add', ['role' => $renamed, 'user_group_id' => $groupId, 'limitation' => ['type' => 'Section', 'values' => ['1']]], null, 'assignment');
        $step('the assignment is described', 'access.role.describe', ['role' => $renamed], static fn (array $d): ?string => count($d['assignments']) === 1 && $d['assignments'][0]['user_group_id'] === $groupId ? null : 'unexpected assignments: ' . json_encode($d['assignments']));

        $step('create a user', 'access.user.create', ['login' => $login, 'email' => $login . '@example.org', 'first_name' => 'Self', 'last_name' => 'Test', 'user_group_id' => $groupId], null, 'user');
        $step('that login is taken', 'access.user.create', ['login' => $login, 'email' => 'other@example.org', 'first_name' => 'A', 'last_name' => 'B', 'user_group_id' => $groupId], null, null, true);
        $step('describe the user, with the role of the group', 'access.user.describe', ['login' => $login], static fn (array $d): ?string => in_array($renamed, $d['roles'], true) && $d['enabled'] ? null : 'roles: ' . implode(',', $d['roles']));
        $step('lock the account', 'access.user.update', ['user_id' => $answers['user']['user_id'] ?? 0, 'enabled' => false, 'first_name' => 'Renamed']);
        $step('the account is locked', 'access.user.describe', ['user_id' => $answers['user']['user_id'] ?? 0], static fn (array $d): ?string => $d['enabled'] ? 'still enabled' : null);
        $step('you cannot lock your own account', 'access.user.update', ['user_id' => $this->repository->getPermissionResolver()->getCurrentUserReference()->getUserId(), 'enabled' => false], null, null, true);
        $step('you cannot delete your own account', 'access.user.delete', ['user_id' => $this->repository->getPermissionResolver()->getCurrentUserReference()->getUserId()], null, null, true);
        $step('delete the user', 'access.user.delete', ['user_id' => $answers['user']['user_id'] ?? 0]);

        $step('remove the assignment', 'access.assignment.remove', ['assignment_id' => $answers['assignment']['assignment_id'] ?? 0]);
        $step('a system role cannot be deleted', 'access.role.delete', ['role' => 'Administrator'], null, null, true);
        $step('delete the role', 'access.role.delete', ['role' => $renamed]);
        $step('delete the copy', 'access.role.delete', ['role' => $copy]);
        $this->removeAccess($stamp);
    }

    /** What the access steps made, removed whatever happened. */
    private function removeAccess(string $stamp): void
    {
        $users = $this->repository->getUserService();
        $roles = $this->repository->getRoleService();
        $attempts = [
            fn () => $users->deleteUser($users->loadUserByLogin('selftest_' . $stamp)),
        ];
        foreach ($users->loadSubUserGroups($users->loadUserGroup(4), 0, 200) as $group) {
            if ($group->getName() === 'Selftest group ' . $stamp) {
                $attempts[] = fn () => $users->deleteUserGroup($group);
            }
        }
        foreach ($roles->loadRoles() as $role) {
            if (str_starts_with($role->identifier, 'selftest_role_' . $stamp)) {
                $attempts[] = fn () => $roles->deleteRole($role);
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
