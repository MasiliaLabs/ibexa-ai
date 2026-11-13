<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Worker;

use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanBuilder;
use Masilia\IbexaAi\Tests\Agent\Block\BlockCatalogFactoryTrait;
use Masilia\IbexaAi\Tests\Agent\Block\ContentCatalogFactoryTrait;
use PHPUnit\Framework\TestCase;

final class PlanBuilderTest extends TestCase
{
    use BlockCatalogFactoryTrait;
    use ContentCatalogFactoryTrait;

    public function testBuildCreateContentPlan(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us', 'subtitle' => 'Our story'],
        ]);

        self::assertSame('create_content', $plan->intent);
        self::assertSame('page', $plan->contentType);
        self::assertSame(42, $plan->parentLocationId);
        self::assertNull($plan->validate());
    }

    public function testBuildUpdateContentPlan(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'update_content',
            'content_id' => 123,
            'fields' => ['title' => 'New Title'],
        ]);

        self::assertSame(123, $plan->contentId);
        self::assertNull($plan->validate());
    }

    public function testBuildTrashContentPlan(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'trash_content',
            'content_id' => 456,
        ]);

        self::assertSame(456, $plan->contentId);
        self::assertNull($plan->validate());
    }

    public function testBuildCreateFolderPlan(): void
    {
        $builder = new PlanBuilder();
        $plan = $builder->build([
            'intent' => 'create_folder',
            'title' => 'Media',
            'parent_location_id' => 1,
        ]);

        self::assertSame('Media', $plan->title);
        self::assertSame(1, $plan->parentLocationId);
        self::assertNull($plan->validate());
    }

    public function testBuildRejectsMissingRequiredFields(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $builder->build(['intent' => 'create_content']);
    }

    public function testBuildRejectsUnknownIntent(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $builder->build(['intent' => 'fly_to_mars']);
    }

    public function testSchemaValidationAcceptsCorrectMatrixColumns(): void
    {
        $catalog = $this->createBlockCatalog([
            'info_cards' => [
                'name' => 'Info Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ibexa_matrix',
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                                ['identifier' => 'body', 'name' => 'Body'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'info_cards',
            'parent_location_id' => 42,
            'fields' => [
                'cards' => [
                    ['icon' => 'star', 'title' => 'Hello', 'body' => 'World'],
                    ['icon' => 'check', 'title' => 'Second', 'body' => 'Row'],
                ],
            ],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testSchemaValidationRejectsUnknownMatrixColumn(): void
    {
        $catalog = $this->createBlockCatalog([
            'info_cards' => [
                'name' => 'Info Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ibexa_matrix',
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "info_cards": matrix field "cards" row 0 has unknown column "body"');
        $this->expectExceptionMessage('Valid columns: icon, title');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'info_cards',
            'parent_location_id' => 42,
            'fields' => [
                'cards' => [
                    ['icon' => 'star', 'title' => 'Hello', 'body' => 'World'],
                ],
            ],
        ]);
    }

    public function testSchemaValidationSkipsUnknownBlockTypes(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => ['title' => 'ibexa_string'],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testSchemaValidationSkipsNonMatrixFields(): void
    {
        $catalog = $this->createBlockCatalog([
            'cta' => [
                'name' => 'CTA',
                'fields' => ['title' => 'ibexa_string', 'image' => 'ibexa_image'],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cta',
            'parent_location_id' => 42,
            'fields' => [
                'title' => 'Hello',
                'image' => ['description' => 'A sunset', 'size' => '1024x1024'],
            ],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testSchemaValidationSkippedWhenCatalogIsNull(): void
    {
        $builder = new PlanBuilder();

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'any_type',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testRequiredFieldValidationRejectsEmptyString(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => [
                    'title' => ['type' => 'ibexa_string', 'required' => true],
                    'subtitle' => ['type' => 'ibexa_string'],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "hero_banner": required field "title" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['title' => '', 'subtitle' => 'A subtitle'],
        ]);
    }

    public function testRequiredFieldValidationRejectsNullValue(): void
    {
        $catalog = $this->createBlockCatalog([
            'cta' => [
                'name' => 'CTA',
                'fields' => [
                    'image' => ['type' => 'ibexa_image', 'required' => true],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "cta": required field "image" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cta',
            'parent_location_id' => 42,
            'fields' => ['image' => null],
        ]);
    }

    public function testRequiredFieldValidationRejectsMissingField(): void
    {
        $catalog = $this->createBlockCatalog([
            'cta' => [
                'name' => 'CTA',
                'fields' => [
                    'title' => ['type' => 'ibexa_string', 'required' => true],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "cta": required field "title" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cta',
            'parent_location_id' => 42,
            'fields' => ['subtitle' => 'optional'],
        ]);
    }

    public function testRequiredFieldValidationAcceptsNonEmptyValue(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => [
                    'title' => ['type' => 'ibexa_string', 'required' => true],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello World'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testRequiredFieldValidationSkipsOptionalEmptyFields(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => [
                    'title' => ['type' => 'ibexa_string', 'required' => true],
                    'subtitle' => ['type' => 'ibexa_string', 'required' => false],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello', 'subtitle' => ''],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testRequiredFieldValidationDetectsEzimageAsAlwaysRequired(): void
    {
        $catalog = $this->createBlockCatalog([
            'hero_banner' => [
                'name' => 'Hero Banner',
                'fields' => [
                    'image' => ['type' => 'ibexa_image'],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "hero_banner": required field "image" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'hero_banner',
            'parent_location_id' => 42,
            'fields' => ['image' => ''],
        ]);
    }

    public function testRequiredFieldValidationDetectsMatrixWithMinimumRows(): void
    {
        $catalog = $this->createBlockCatalog([
            'cards' => [
                'name' => 'Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ibexa_matrix',
                        'required' => true,
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "cards": required field "cards" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cards',
            'parent_location_id' => 42,
            'fields' => ['cards' => []],
        ]);
    }

    public function testRequiredFieldValidationAcceptsNonEmptyMatrix(): void
    {
        $catalog = $this->createBlockCatalog([
            'cards' => [
                'name' => 'Cards',
                'fields' => [
                    'cards' => [
                        'type' => 'ibexa_matrix',
                        'required' => true,
                        'settings' => [
                            'columns' => [
                                ['identifier' => 'icon', 'name' => 'Icon'],
                                ['identifier' => 'title', 'name' => 'Title'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder($catalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'cards',
            'parent_location_id' => 42,
            'fields' => [
                'cards' => [['icon' => 'star', 'title' => 'Hello']],
            ],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testRequiredFieldValidationSkippedWhenCatalogIsNull(): void
    {
        $builder = new PlanBuilder();

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'any_type',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testCreatePageRejectsEmptyContentType(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('create_content requires contentType');

        $builder->build([
            'intent' => 'create_content',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);
    }

    public function testCreatePageRejectsEmptyFields(): void
    {
        $builder = new PlanBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('create_content requires non-empty fields');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
        ]);
    }

    // --- ContentCatalog: standard content type validation ---

    public function testContentCatalogValidatesRequiredFieldsForPage(): void
    {
        $contentCatalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => ['type' => 'ibexa_string', 'required' => true],
                        'description' => 'ibexa_text',
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder(schemas: $contentCatalog);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content type "page": required field "title" is empty');

        $builder->build([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['description' => 'Some description'],
        ]);
    }

    public function testContentCatalogAcceptsValidPagePlan(): void
    {
        $contentCatalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'title' => ['type' => 'ibexa_string', 'required' => true],
                        'description' => 'ibexa_text',
                    ],
                ],
            ],
        ]);
        $builder = new PlanBuilder(schemas: $contentCatalog);

        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'page',
            'parent_location_id' => 42,
            'fields' => ['title' => 'About Us', 'description' => 'Our story'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }

    public function testContentCatalogSkipsUnknownContentTypes(): void
    {
        $contentCatalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => ['title' => ['type' => 'ibexa_string', 'required' => true]],
                ],
            ],
        ]);
        $builder = new PlanBuilder(schemas: $contentCatalog);

        // 'unknown_type' is not in any catalog — validation is skipped
        $plan = $builder->build([
            'intent' => 'create_content',
            'content_type' => 'unknown_type',
            'parent_location_id' => 42,
            'fields' => ['title' => 'Hello'],
        ]);

        self::assertSame('create_content', $plan->intent);
    }
}
