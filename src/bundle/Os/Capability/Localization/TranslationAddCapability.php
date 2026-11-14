<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Localization;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Agent\Tool\ContentUpdater;
use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

/**
 * Writes a content in a language it does not have yet, and publishes it. The values are written with the same
 * converters as content.update, so a field takes the same formats.
 */
final class TranslationAddCapability extends RepositoryCapability
{
    public function __construct(Repository $repository, private readonly ContentUpdater $updater)
    {
        parent::__construct($repository);
    }

    public function name(): string
    {
        return 'localization.translation.add';
    }

    public function summary(): string
    {
        return 'Add a translation to a content: write its fields in a language it does not have yet.';
    }

    public function description(): string
    {
        return 'Creates the translation and publishes a new version of the content. Give the values of the fields to translate (read the content with content.load first, '
            . 'and translate what the user asked for); fields you leave out stay empty in the new language, and fall back to the main language when shown. '
            . 'The language must be enabled (see localization.language.list) and the content must not already have it: to change an existing translation use content.update with its language.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content_id', 'language_code', 'fields'],
            'properties' => [
                'content_id' => ['type' => 'integer', 'minimum' => 1],
                'language_code' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10, 'description' => 'Language code, e.g. fre-FR'],
                'fields' => ['type' => 'object', 'description' => 'Field identifier => value, in the new language'],
            ],
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'edit'], ['content', 'publish']];
    }

    public function examples(): array
    {
        return [['description' => 'Translate a page into French', 'arguments' => ['content_id' => 1353, 'language_code' => 'fre-FR', 'fields' => ['title' => 'Bonjour']]]];
    }

    public function validate(array $arguments): ?string
    {
        if ((array) $arguments['fields'] === []) {
            return 'fields is empty: give the values to write in the new language.';
        }
        try {
            $content = $this->repository->getContentService()->loadContent((int) $arguments['content_id']);
        } catch (Throwable) {
            return sprintf('The content %d does not exist.', (int) $arguments['content_id']);
        }
        try {
            $language = $this->repository->getContentLanguageService()->loadLanguage((string) $arguments['language_code']);
        } catch (Throwable) {
            return sprintf('There is no language "%s": see localization.language.list.', $arguments['language_code']);
        }
        if (!$language->isEnabled()) {
            return sprintf('The language "%s" is disabled: enable it with localization.language.update.', $arguments['language_code']);
        }
        if (in_array($arguments['language_code'], $content->getVersionInfo()->languageCodes, true)) {
            return sprintf('The content already has a "%s" translation: change it with content.update.', $arguments['language_code']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        try {
            $name = $this->repository->getContentService()->loadContentInfo((int) $arguments['content_id'])->name;
        } catch (Throwable) {
            $name = '';
        }

        return new Preview(
            'Add a translation',
            [
                ['Content', $name !== '' ? sprintf('%s (content %d)', $name, (int) $arguments['content_id']) : (string) (int) $arguments['content_id']],
                ['Language', (string) $arguments['language_code']],
                ['Fields', implode(', ', array_map('strval', array_slice(array_keys((array) $arguments['fields']), 0, 14)))],
            ],
            ['A new version is published.'],
        );
    }

    public function execute(array $arguments): CapabilityResult
    {
        $content = $this->updater->update((int) $arguments['content_id'], (array) $arguments['fields'], (string) $arguments['language_code']);

        return CapabilityResult::ok(
            sprintf('"%s" now has a "%s" translation', $content->getName(), $arguments['language_code']),
            ['content_id' => $content->id, 'languages' => $content->getVersionInfo()->languageCodes],
        );
    }
}
