<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\Content\Search\SearchHit;
use Ibexa\Contracts\Core\Repository\Values\Content\Search\SearchResult;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo as VersionInfoContract;
use Ibexa\Contracts\Core\Variation\Values\ImageVariation;
use Ibexa\Contracts\Core\Variation\Values\Variation;
use Ibexa\Contracts\Core\Variation\VariationHandler;
use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Ibexa\Core\Repository\Values\Content\Content;
use Ibexa\Core\Repository\Values\Content\VersionInfo;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Input\NovaSeoMetasInput;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * og:image / twitter:image are resolved from the media library. The lookup swallows every exception and logs a
 * warning, so a broken read shows up only as an empty meta: these tests use real Content objects, whose magic
 * __get throws on an unknown property, instead of mocks that would answer null.
 */
final class NovaSeoMetasInputTest extends TestCase
{
    private function libraryImage(string $uri, string $language): Content
    {
        return new Content([
            'versionInfo' => new VersionInfo([
                'contentInfo' => new ContentInfo(['id' => 77, 'mainLanguageCode' => $language]),
                'languageCodes' => [$language],
            ]),
            'internalFields' => [
                new Field(['fieldDefIdentifier' => 'image', 'value' => new ImageValue(['uri' => $uri]), 'languageCode' => $language]),
            ],
            'prioritizedFieldLanguageCode' => $language,
        ]);
    }

    /**
     * @param list<Content> $found
     */
    private function transformer(array $found, VariationHandler $variations, object $logger): NovaSeoMetasInput
    {
        $search = $this->createMock(SearchService::class);
        $search->method('findContent')->willReturn(new SearchResult([
            'searchHits' => array_map(static fn (Content $content): SearchHit => new SearchHit(['valueObject' => $content]), $found),
            'totalCount' => count($found),
        ]));
        $repository = $this->createMock(Repository::class);
        $repository->method('getSearchService')->willReturn($search);

        return new NovaSeoMetasInput($repository, $variations, $logger);
    }

    private function resolve(NovaSeoMetasInput $transformer, string $description): string
    {
        $method = new \ReflectionMethod($transformer, 'resolveImageMeta');

        return $method->invoke($transformer, $description);
    }

    private function logger(): object
    {
        return new class() extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lines[] = $level . ': ' . $message;
            }
        };
    }

    public function testAnImageOfTheLibraryGivesItsVariationUriWithoutAnyWarning(): void
    {
        $handler = new class() implements VariationHandler {
            public ?string $language = null;
            public ?int $versionContentId = null;
            public ?string $variationName = null;

            public function getVariation(Field $field, VersionInfoContract $versionInfo, $variationName, array $parameters = []): Variation
            {
                $this->language = $field->getLanguageCode();
                $this->versionContentId = $versionInfo->getContentInfo()->getId();
                $this->variationName = $variationName;

                return new ImageVariation(['uri' => '/var/storage/images/social.jpg']);
            }
        };
        $logger = $this->logger();

        $uri = $this->resolve($this->transformer([$this->libraryImage('/orig.jpg', 'fre-FR')], $handler, $logger), 'logo');

        self::assertSame('/var/storage/images/social.jpg', $uri);
        self::assertSame([], $logger->lines, 'nothing may fail silently');
        self::assertSame('fre-FR', $handler->language, 'the variation is built for the language of the image');
        self::assertSame(77, $handler->versionContentId);
        self::assertSame('social_network_image', $handler->variationName);
    }

    public function testNothingFoundGivesAnEmptyMeta(): void
    {
        $handler = $this->createMock(VariationHandler::class);

        self::assertSame('', $this->resolve($this->transformer([], $handler, $this->logger()), 'nothing'));
        self::assertSame('', $this->resolve($this->transformer([], $handler, $this->logger()), ''));
    }
}
