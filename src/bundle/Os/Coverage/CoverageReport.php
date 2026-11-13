<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Coverage;

use Masilia\IbexaAi\Os\CapabilityRegistry;
use Symfony\Component\Routing\RouterInterface;

/**
 * The admin routes of Ibexa against the capabilities of the AI OS (see {@see CoverageMatrix}): what is covered, what is
 * planned, what is not an action of an administrator, and what nobody has answered for.
 */
final readonly class CoverageReport
{
    public const FILE = __DIR__ . '/../../Resources/config/os_coverage.yaml';

    public function __construct(
        private RouterInterface $router,
        private CapabilityRegistry $registry,
    ) {
    }

    /**
     * @return array{
     *     routes: int,
     *     covered: int,
     *     planned: int,
     *     out_of_scope: int,
     *     unmapped: list<string>,
     *     stale: list<string>,
     *     planned_routes: array<string, string>,
     *     areas: array<string, array{covered?: int, planned?: int, out_of_scope?: int, unmapped?: int}>
     * }
     */
    public function build(): array
    {
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

        return [
            'routes' => count($routes),
            'covered' => count($result['covered']),
            'planned' => count($result['planned']),
            'out_of_scope' => count($result['out_of_scope']),
            'unmapped' => $result['unmapped'],
            'stale' => $result['stale'],
            'planned_routes' => $result['planned'],
            'areas' => $areas,
        ];
    }
}
