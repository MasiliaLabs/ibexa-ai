<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Attachment;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Masilia\IbexaAi\Chat\DTO\Attachment;

/**
 * The images users attach to their messages: the file on disk, who owns it, and its size.
 *
 * What is accepted is decided here, from the file itself and not from what the browser says it is: a real
 * JPEG, PNG, GIF or WebP, not too heavy, not too large in pixels. Every read is for one user. Old attachments are
 * removed when a new one comes in.
 */
class AttachmentStore
{
    public const TABLE = 'app_ai_attachment';
    public const MAX_BYTES = 5 * 1024 * 1024;
    /** A decompression bomb is a small file with an enormous picture in it. */
    public const MAX_PIXELS = 40_000_000;
    public const MAX_SIDE = 12_000;
    public const KEEP_DAYS = 7;

    private const TYPES = [IMAGETYPE_JPEG => ['image/jpeg', 'jpg'], IMAGETYPE_PNG => ['image/png', 'png'], IMAGETYPE_GIF => ['image/gif', 'gif'], IMAGETYPE_WEBP => ['image/webp', 'webp']];
    private const FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $attachmentsDirectory,
    ) {
    }

    /**
     * @throws AttachmentRejectedException
     */
    public function store(string $tmpPath, string $originalName, int $userId, ?DateTimeImmutable $now = null): Attachment
    {
        $size = @filesize($tmpPath);
        if ($size === false || $size === 0) {
            throw new AttachmentRejectedException('The file is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new AttachmentRejectedException(sprintf('The image is too large (up to %d MB).', self::MAX_BYTES / 1024 / 1024));
        }

        $info = @getimagesize($tmpPath);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            throw new AttachmentRejectedException('Only JPEG, PNG, GIF and WebP images can be attached.');
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            throw new AttachmentRejectedException('The image is not valid, or has too many pixels.');
        }
        [$mime, $extension] = self::TYPES[$info[2]];

        $now = ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));
        $this->purge($now);

        $id = self::uuid();
        $filename = $id . '.' . $extension;
        if (!is_dir($this->attachmentsDirectory) && !@mkdir($this->attachmentsDirectory, 0700, true) && !is_dir($this->attachmentsDirectory)) {
            throw new AttachmentRejectedException('The image could not be kept.');
        }
        if (!@copy($tmpPath, $this->attachmentsDirectory . '/' . $filename)) {
            throw new AttachmentRejectedException('The image could not be kept.');
        }

        $name = self::cleanName($originalName);
        $this->connection->insert(self::TABLE, [
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'mime' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'filename' => $filename,
            'created_at' => $now->format(self::FORMAT),
        ]);

        return new Attachment($id, $userId, $name, $mime, $size, $width, $height, $filename);
    }

    /** The user's attachment, or null (none by this id, or someone else's). */
    public function find(string $id, int $userId): ?Attachment
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE id = ? AND user_id = ?', [$id, $userId]);

        return $row === false ? null : new Attachment(
            (string) $row['id'],
            (int) $row['user_id'],
            (string) $row['name'],
            (string) $row['mime'],
            (int) $row['size'],
            (int) $row['width'],
            (int) $row['height'],
            (string) $row['filename'],
        );
    }

    public function path(Attachment $attachment): string
    {
        // The file name is one the store made itself: nothing of the user's reaches the path.
        return $this->attachmentsDirectory . '/' . basename($attachment->filename);
    }

    /** What an attachment looks like when it is shown by its id: only a well-formed id is looked up. */
    public static function isValidId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) === 1;
    }

    public static function cleanName(string $name): string
    {
        $clean = trim((string) preg_replace('/[\x00-\x1F"<>\\\\\/]+/u', ' ', basename(str_replace('\\', '/', $name))));

        return $clean === '' ? 'image' : mb_substr($clean, 0, 120);
    }

    private function purge(DateTimeImmutable $now): void
    {
        $limit = $now->modify(sprintf('-%d days', self::KEEP_DAYS))->format(self::FORMAT);
        $old = $this->connection->fetchAllAssociative('SELECT id, filename FROM ' . self::TABLE . ' WHERE created_at < ?', [$limit]);
        foreach ($old as $row) {
            @unlink($this->attachmentsDirectory . '/' . basename((string) $row['filename']));
            $this->connection->delete(self::TABLE, ['id' => $row['id']]);
        }
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
