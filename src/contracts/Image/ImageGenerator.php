<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Image;

/**
 * Makes an image from a description, with the image model the siteaccess is set to use (the AI settings of the admin).
 * Whatever the provider, the answer is the bytes of the image and their media type.
 */
interface ImageGenerator
{
    public function isConfigured(): bool;

    /**
     * @throws \Throwable on network / API / configuration failure
     */
    public function generate(
        string $prompt,
        ?string $size = null,
        ?string $quality = null,
    ): ImageGenerationResult;
}
