<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Block;

use Masilia\Contracts\IbexaAi\Content\ContentTypeSchemas as ContentTypeSchemasContract;

/**
 * Where the plan builder reads the schema of a content type, to refuse a change that cannot be saved before the user is
 * asked to approve it. The name the plan builder knows it by; the type is {@see ContentTypeSchemasContract} (1.0 removes
 * this one with the plan builder).
 */
interface ContentTypeSchemaSource extends ContentTypeSchemasContract
{
}
