<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Localization;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class TranslationRemoveCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'localization.translation.remove';
    }

    public function summary(): string
    {
        return 'Delete one translation of a content, in every version.';
    }

    public function description(): string
    {
        return 'Deletes the content’s translation in the given language from all its versions. The main language of the content cannot be deleted.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id', 'language_code'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1], 'language_code' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10, 'description' => 'Language code, e.g. fre-FR']]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['content', 'remove']];
    }

    public function examples(): array
    {
        return [['description' => 'Remove the Arabic version of a page', 'arguments' => ['content_id' => 1353, 'language_code' => 'ara-SA']]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $info = $this->repository->getContentService()->loadContentInfo((int) $arguments['content_id']);
        } catch (\Throwable) {
            return sprintf('The content %d does not exist.', (int) $arguments['content_id']);
        }

        return $info->mainLanguageCode === $arguments['language_code'] ? 'That is the main language of the content: change it first with localization.set_main_language.' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a translation', [['Content', (string) (int) $arguments['content_id']], ['Language', (string) $arguments['language_code']]], ['The translation is deleted from every version of the content. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentService();
        $info = $service->loadContentInfo((int) $arguments['content_id']);
        $service->deleteTranslation($info, (string) $arguments['language_code']);

        return CapabilityResult::ok(sprintf('The "%s" translation of "%s" was deleted', $arguments['language_code'], $info->name), ['content_id' => $info->id]);
    }
}
