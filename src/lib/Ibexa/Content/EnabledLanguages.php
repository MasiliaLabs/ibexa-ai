<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Content;

use Ibexa\Contracts\Core\Repository\LanguageService;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Content\LanguageCode;

final readonly class EnabledLanguages implements Languages
{
    public function __construct(private LanguageService $languages)
    {
    }

    public function resolve(string $requested): ?string
    {
        $codes = [];
        foreach ($this->languages->loadLanguages() as $language) {
            if ($language->enabled) {
                $codes[] = $language->languageCode;
            }
        }

        return LanguageCode::resolve(trim($requested), $codes);
    }

    public function default(): string
    {
        return $this->languages->getDefaultLanguageCode();
    }
}
