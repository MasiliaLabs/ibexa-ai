<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAi\Ibexa\Field\Input\NovaSeoMetasInput;
use PHPUnit\Framework\TestCase;

/** What the model gives for a Nova SEO field becomes the Metas the field stores, each content kept as given: no image is searched for. */
final class NovaSeoMetasInputTest extends TestCase
{
    public function testEachKeyBecomesAMetaWithItsContentAsGiven(): void
    {
        $value = (new NovaSeoMetasInput())->transform($this->createMock(FieldDefinition::class), [
            'title' => 'A page',
            'og:image' => '/var/site/storage/images/logo.jpg',
            'description' => 'About it',
        ]);

        $metas = [];
        foreach ($value->metas as $meta) {
            $metas[$meta->getName()] = $meta->getContent();
        }

        self::assertSame(['title' => 'A page', 'og:image' => '/var/site/storage/images/logo.jpg', 'description' => 'About it'], $metas);
    }

    public function testAValueThatIsNotAnObjectIsLeftToIbexaToRefuse(): void
    {
        self::assertSame('plain', (new NovaSeoMetasInput())->transform($this->createMock(FieldDefinition::class), 'plain'));
    }
}
