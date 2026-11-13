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
            $step('trash A', 'content.trash', ['content_id' => $a['content_id']]);
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
        }

        if ($failed > 0) {
            $io->error(sprintf('%d step(s) failed.', $failed));

            return Command::FAILURE;
        }
        $io->success('All steps passed; the throwaway folders are gone.');

        return Command::SUCCESS;
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
        foreach ($trash->findTrashItems($query)->items as $item) {
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
