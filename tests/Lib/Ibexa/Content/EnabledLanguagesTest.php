<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Ibexa\Content;

use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Masilia\IbexaAi\Ibexa\Content\EnabledLanguages;
use PHPUnit\Framework\TestCase;

final class EnabledLanguagesTest extends TestCase
{
    private function languages(): EnabledLanguages
    {
        $service = $this->createMock(LanguageService::class);
        $service->method('loadLanguages')->willReturn([
            new Language(['languageCode' => 'eng-GB', 'enabled' => true]),
            new Language(['languageCode' => 'fre-FR', 'enabled' => true]),
            new Language(['languageCode' => 'ara-SA', 'enabled' => false]),
        ]);
        $service->method('getDefaultLanguageCode')->willReturn('eng-GB');

        return new EnabledLanguages($service);
    }

    public function testACodeOrALanguageWhateverItsRegionIsAnEnabledLanguage(): void
    {
        $languages = $this->languages();

        self::assertSame('fre-FR', $languages->resolve('fre-FR'));
        self::assertSame('fre-FR', $languages->resolve(' fr '));
        self::assertSame('eng-GB', $languages->resolve('en'));
        self::assertSame('eng-GB', $languages->default());
    }

    public function testADisabledOrUnknownLanguageIsNone(): void
    {
        self::assertNull($this->languages()->resolve('ar'));
        self::assertNull($this->languages()->resolve('xx'));
    }
}
