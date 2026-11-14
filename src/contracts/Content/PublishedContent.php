<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;

/** A content that was just created and published, and its main location (null for a content created without one). */
final readonly class PublishedContent
{
    public function __construct(
        public Content $content,
        public ?Location $location,
    ) {
    }
}
