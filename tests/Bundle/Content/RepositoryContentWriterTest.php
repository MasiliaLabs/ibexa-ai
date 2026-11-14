<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Content;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Masilia\Bundle\IbexaAi\Agent\Tool\ContentCreator;
use Masilia\Bundle\IbexaAi\Agent\Tool\ContentUpdater;
use Masilia\Bundle\IbexaAi\Agent\Tool\FieldValueTransformerRegistry;
use Masilia\Bundle\IbexaAi\Content\Write\RepositoryContentWriter;
use PHPUnit\Framework\TestCase;

final class RepositoryContentWriterTest extends TestCase
{
    public function testAContentTypeThatDoesNotExistIsSaidNotAnsweredWithNothing(): void
    {
        $types = $this->createMock(ContentTypeService::class);
        $types->method('loadContentTypeByIdentifier')->willThrowException(new NotFoundException('content type', 'nope'));
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentTypeService')->willReturn($types);
        $registry = new FieldValueTransformerRegistry([]);
        $writer = new RepositoryContentWriter(new ContentCreator($repository, $registry), (new \ReflectionClass(ContentUpdater::class))->newInstanceWithoutConstructor());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no content type "nope".');

        $writer->create('nope', [2], [], 'eng-GB');
    }
}
