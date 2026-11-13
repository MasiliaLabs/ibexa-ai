<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Command;

use Masilia\Bundle\IbexaAi\Os\Coverage\CoverageMatrix;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'masilia:ai:os:coverage',
    description: 'Compare the admin routes of Ibexa with the capabilities of the AI OS.',
)]
final class OsCoverageCommand extends Command
{
    private const FILE = __DIR__ . '/../Resources/config/os_coverage.yaml';

    public function __construct(
        private readonly RouterInterface $router,
        private readonly CapabilityRegistry $registry,
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

        $routes = array_values(array_filter(
            array_keys($this->router->getRouteCollection()->all()),
            static fn (string $name): bool => str_starts_with($name, 'ibexa.') && !str_starts_with($name, 'ibexa.rest.'),
        ));
        sort($routes);

        $result = CoverageMatrix::fromFile(self::FILE)->classify($routes, fn (string $name): bool => $this->registry->get($name) !== null);

        $areas = [];
        foreach (['covered', 'planned', 'out_of_scope'] as $outcome) {
            foreach (array_keys($result[$outcome]) as $route) {
                $area = explode('.', $route)[1] ?? $route;
                $areas[$area][$outcome] = ($areas[$area][$outcome] ?? 0) + 1;
            }
        }
        foreach ($result['unmapped'] as $route) {
            $area = explode('.', $route)[1] ?? $route;
            $areas[$area]['unmapped'] = ($areas[$area]['unmapped'] ?? 0) + 1;
        }
        ksort($areas);

        $io->table(
            ['Area', 'Covered', 'Planned', 'Out of scope', 'Unmapped'],
            array_map(
                static fn (string $area, array $counts): array => [$area, $counts['covered'] ?? 0, $counts['planned'] ?? 0, $counts['out_of_scope'] ?? 0, $counts['unmapped'] ?? 0],
                array_keys($areas),
                $areas,
            ),
        );
        $io->writeln(sprintf(
            '%d admin routes: %d covered, %d planned, %d out of scope, %d unmapped.',
            count($routes),
            count($result['covered']),
            count($result['planned']),
            count($result['out_of_scope']),
            count($result['unmapped']),
        ));

        if ($input->getOption('missing') && $result['planned'] !== []) {
            $io->section('Capability not written yet');
            $io->table(['Route', 'Capability'], array_map(null, array_keys($result['planned']), array_values($result['planned'])));
        }
        if ($result['unmapped'] !== []) {
            $io->section('Unmapped: give each a capability or a reason in os_coverage.yaml');
            $io->listing($result['unmapped']);
        }
        if ($result['stale'] !== [] && $output->isVerbose()) {
            $io->section('Entries that match no route (a bundle that is not installed?)');
            $io->listing($result['stale']);
        }

        return $input->getOption('strict') && $result['unmapped'] !== [] ? Command::FAILURE : Command::SUCCESS;
    }
}
