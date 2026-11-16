<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Chat;

use Masilia\IbexaAi\Chat\Mention\ContentMentions;
use Masilia\IbexaAi\Chat\Thread\ThreadTitle;
use PHPUnit\Framework\TestCase;

final class ContentMentionsTest extends TestCase
{
    public function testWhatIsTypedIsReducedToPlainWords(): void
    {
        self::assertSame("L'énergie - 2040", ContentMentions::cleanQuery("  L'énergie *  % - 2040\n"));
        self::assertSame('', ContentMentions::cleanQuery('*%?'));
        self::assertSame(40, mb_strlen(ContentMentions::cleanQuery(str_repeat('é', 100))));
    }

    public function testNamesThatStartWithWhatWasTypedComeFirst(): void
    {
        $ranked = ContentMentions::rank(
            [['name' => 'The page about acmes'], ['name' => 'Acme bags'], ['name' => 'About acmes'], ['name' => 'acme fuels']],
            'Acme',
        );

        self::assertSame(['Acme bags', 'acme fuels', 'About acmes', 'The page about acmes'], array_column($ranked, 'name'));
    }

    public function testADocumentIsNotPartOfTheTitle(): void
    {
        $message = "Write a page from this\n\n<attachment name=\"brief.md\" bytes=\"12\">\n# Brief\nBody\n</attachment>";

        self::assertSame('Write a page from this', ThreadTitle::from($message));
        self::assertSame('Attached: brief.md', ThreadTitle::from("<attachment name=\"brief.md\" bytes=\"12\">\nx\n</attachment>"));
    }

    public function testAMentionInAFirstMessageReadsAsItsNameInTheTitle(): void
    {
        self::assertSame('Translate @Les atouts to French', ThreadTitle::from('Translate @[Les atouts](content:1353/1343) to French'));
        self::assertSame('Check @About', ThreadTitle::from('Check @[About](content:9)'));
        self::assertSame('Not a mention @[x](https://x.test)', ThreadTitle::from('Not a mention @[x](https://x.test)'));
    }
}
