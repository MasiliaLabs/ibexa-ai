<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\DTO;

/**
 * An image the user attached to a message, kept on the server for the conversation.
 */
final readonly class Attachment
{
    public function __construct(
        public string $id,
        public int $userId,
        public string $name,
        public string $mime,
        public int $size,
        public int $width,
        public int $height,
        public string $filename,
    ) {
    }

    /**
     * @return array{id: string, name: string, mime: string, size: int, width: int, height: int}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'mime' => $this->mime, 'size' => $this->size, 'width' => $this->width, 'height' => $this->height];
    }
}
