<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Ibexa\Content;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Write\ContentCreator;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Write\ContentUpdater;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Input\FieldInputs;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Write\RepositoryContentWriter;
use PHPUnit\Framework\TestCase;

final class RepositoryContentWriterTest extends TestCase
{
    public function testAContentTypeThatDoesNotExistIsSaidNotAnsweredWithNothing(): void
    {
        $types = $this->createMock(ContentTypeService::class);
        $types->method('loadContentTypeByIdentifier')->willThrowException(new NotFoundException('content type', 'nope'));
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentTypeService')->willReturn($types);
        $registry = new FieldInputs([]);
        $writer = new RepositoryContentWriter(new ContentCreator($repository, $registry), (new \ReflectionClass(ContentUpdater::class))->newInstanceWithoutConstructor());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no content type "nope".');

        $writer->create('nope', [2], [], 'eng-GB');
    }
}
