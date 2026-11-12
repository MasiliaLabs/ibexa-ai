<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Worker;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\Repository\Values\Content\Location;
use InvalidArgumentException;
use Masilia\Bundle\IbexaAi\Agent\Tool\SiteaccessLocationResolver;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanBuilder;
use PHPUnit\Framework\TestCase;

/**
 * A plan must be refused before any approval when it points at something that does not exist
 * or at another site than its siteaccess. Locations: 1016 is the Fossil Exit site, 1018 its home,
 * 311 another site, 43 the media root (outside every site).
 */
final class PlanBuilderTargetsTest extends TestCase
{
    public function testAnUnknownContentTypeIsRefusedBeforeApproval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "licorne" does not exist');

        $this->builder()->build($this->plan(['content_type' => 'licorne']));
    }

    public function testAKnownContentTypeIsAccepted(): void
    {
        $plan = $this->builder()->build($this->plan(['content_type' => 'article']));

        self::assertSame('article', $plan->contentType);
    }

    public function testAMissingParentLocationIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent location 999999 does not exist');

        $this->builder()->build($this->plan(['parent_location_id' => 999999]));
    }

    public function testAParentInAnotherSiteIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent location 311 is under the site at location 311, but siteaccess "fossil_exit" belongs to the site at location 1016');

        $this->builder()->build($this->plan(['parent_location_id' => 311]));
    }

    public function testAParentInTheSameSiteOutsideTheHomeIsAccepted(): void
    {
        // Taxonomies sit under the site but not under the siteaccess tree root.
        $plan = $this->builder()->build($this->plan(['parent_location_id' => 1304]));

        self::assertSame(1304, $plan->parentLocationId);
    }

    public function testAParentOutsideEverySiteIsAccepted(): void
    {
        $plan = $this->builder()->build($this->plan(['parent_location_id' => 43]));

        self::assertSame(43, $plan->parentLocationId);
    }

    public function testAFolderPlanChecksItsParentToo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent location 999999 does not exist');

        $this->builder()->build([
            'intent' => 'create_folder',
            'title' => 'Essai',
            'siteaccess' => 'fossil_exit',
            'parent_location_id' => 999999,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function plan(array $overrides): array
    {
        return $overrides + [
            'intent' => 'create_content',
            'content_type' => 'article',
            'title' => 'Essai',
            'siteaccess' => 'fossil_exit',
            'parent_location_id' => 1018,
            'fields' => ['title' => 'Essai'],
        ];
    }

    private function builder(): PlanBuilder
    {
        $paths = [
            1018 => '/1/2/1016/1018/',
            1304 => '/1/2/1016/1304/',
            311 => '/1/2/311/',
            43 => '/1/43/',
        ];

        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->willReturnCallback(
            function (int $id) use ($paths): Location {
                if (!isset($paths[$id])) {
                    throw new NotFoundException('location', (string) $id);
                }

                return new Location(['id' => $id, 'pathString' => $paths[$id]]);
            },
        );

        $types = $this->createMock(ContentTypeService::class);
        $types->method('loadContentTypeByIdentifier')->willReturnCallback(
            function (string $identifier): ContentType {
                if ($identifier !== 'article') {
                    throw new NotFoundException('content type', $identifier);
                }

                return $this->createMock(ContentType::class);
            },
        );

        $repository = $this->createMock(Repository::class);
        $repository->method('getLocationService')->willReturn($locations);
        $repository->method('getContentTypeService')->willReturn($types);

        $resolver = $this->createMock(SiteaccessLocationResolver::class);
        $resolver->method('resolve')->willReturn(1018);

        return new PlanBuilder(repository: $repository, siteaccessResolver: $resolver);
    }
}
