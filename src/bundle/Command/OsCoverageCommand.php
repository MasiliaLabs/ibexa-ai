<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Command;

use Masilia\IbexaAi\Os\Coverage\CoverageReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'masilia:ai:os:coverage',
    description: 'Compare the admin routes of Ibexa with the capabilities of the AI OS.',
)]
final class OsCoverageCommand extends Command
{
    public function __construct(
        private readonly CoverageReport $report,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail when a route is neither mapped to a capability nor justified as out of scope.')
            ->addOption('missing', null, InputOption::VALUE_NONE, 'List the routes whose capability does not exist yet.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $report = $this->report->build();

        $io->table(
            ['Area', 'Covered', 'Planned', 'Out of scope', 'Unmapped'],
            array_map(
                static fn (string $area, array $counts): array => [$area, $counts['covered'] ?? 0, $counts['planned'] ?? 0, $counts['out_of_scope'] ?? 0, $counts['unmapped'] ?? 0],
                array_keys($report['areas']),
                $report['areas'],
            ),
        );
        $io->writeln(sprintf(
            '%d admin routes: %d covered, %d planned, %d out of scope, %d unmapped.',
            $report['routes'],
            $report['covered'],
            $report['planned'],
            $report['out_of_scope'],
            count($report['unmapped']),
        ));

        if ($input->getOption('missing') && $report['planned_routes'] !== []) {
            $io->section('Capability not written yet');
            $io->table(['Route', 'Capability'], array_map(null, array_keys($report['planned_routes']), array_values($report['planned_routes'])));
        }
        if ($report['unmapped'] !== []) {
            $io->section('Unmapped: give each a capability or a reason in os_coverage.yaml');
            $io->listing($report['unmapped']);
        }
        if ($report['stale'] !== [] && $output->isVerbose()) {
            $io->section('Entries that match no route (a bundle that is not installed?)');
            $io->listing($report['stale']);
        }

        return $input->getOption('strict') && $report['unmapped'] !== [] ? Command::FAILURE : Command::SUCCESS;
    }
}
