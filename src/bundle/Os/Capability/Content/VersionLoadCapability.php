<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Risk;

final class VersionLoadCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'content.version.load';
    }

    public function summary(): string
    {
        return 'List the versions of a content, or read one version.';
    }

    public function description(): string
    {
        return 'Without version_no: every version with its status (draft, published, archived), languages, author and date. With version_no: that version\’s fields as text.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1], 'version_no' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'versionread']];
    }

    public function examples(): array
    {
        return [['description' => 'The history of a page', 'arguments' => ['content_id' => 1353]]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getContentService();
        $info = $service->loadContentInfo((int) $arguments['content_id']);
        $status = [0 => 'draft', 1 => 'published', 2 => 'archived'];
        if (!isset($arguments['version_no'])) {
            $rows = [];
            foreach ($service->loadVersions($info) as $version) {
                $rows[] = ['version_no' => $version->versionNo, 'status' => $status[$version->status] ?? 'other', 'languages' => $version->languageCodes, 'author' => $version->creatorId, 'modified' => $version->modificationDate->format('Y-m-d H:i')];
            }

            return CapabilityResult::ok(sprintf('%d version(s) of "%s"', count($rows), $info->name), ['versions' => $rows]);
        }
        $content = $service->loadContent($info->id, null, (int) $arguments['version_no']);
        $fields = [];
        foreach ($content->getFields() as $field) {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $field->value)) ?? '');
            if ($text !== '') {
                $fields[$field->fieldDefIdentifier . ' (' . $field->languageCode . ')'] = mb_substr($text, 0, 300);
            }
        }

        return CapabilityResult::ok(sprintf('Version %d of "%s"', (int) $arguments['version_no'], $info->name), ['version_no' => (int) $arguments['version_no'], 'fields' => $fields]);
    }
}
