<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Localization;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class SetMainLanguageCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'localization.set_main_language';
    }

    public function summary(): string
    {
        return 'Choose the main language of a content.';
    }

    public function description(): string
    {
        return 'The main language is the one shown when a translation is missing. The content must already have a translation in it.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id', 'language_code'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1], 'language_code' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 10, 'description' => 'Language code, e.g. fre-FR']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'edit']];
    }

    public function examples(): array
    {
        return [['description' => 'Make French the main language', 'arguments' => ['content_id' => 1353, 'language_code' => 'fre-FR']]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $content = $this->repository->getContentService()->loadContent((int) $arguments['content_id']);
        } catch (\Throwable) {
            return sprintf('The content %d does not exist.', (int) $arguments['content_id']);
        }

        return in_array($arguments['language_code'], $content->getVersionInfo()->languageCodes, true) ? null : sprintf('The content has no translation in "%s" (it has: %s).', $arguments['language_code'], implode(', ', $content->getVersionInfo()->languageCodes));
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Change the main language', [['Content', (string) (int) $arguments['content_id']], ['New main language', (string) $arguments['language_code']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentService();
        $info = $service->loadContentInfo((int) $arguments['content_id']);
        $struct = $service->newContentMetadataUpdateStruct();
        $struct->mainLanguageCode = (string) $arguments['language_code'];
        $service->updateContentMetadata($info, $struct);

        return CapabilityResult::ok(sprintf('The main language of "%s" is now "%s"', $info->name, $arguments['language_code']), ['content_id' => $info->id]);
    }
}
