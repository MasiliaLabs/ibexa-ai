<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat\Attachment;

/**
 * An image as it is sent to the model: not larger than what a model reads, so that it does not weigh on every
 * request of the conversation (it stays in the history).
 */
final class ImageForModel
{
    public const MAX_SIDE = 1568;
    public const MAX_BYTES = 1_500_000;

    /**
     * @return array{data: string, mime: string}|null Null when the file cannot be read as an image
     */
    public static function prepare(string $path, string $mime, int $width, int $height): ?array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            return null;
        }
        if (max($width, $height) <= self::MAX_SIDE && strlen($bytes) <= self::MAX_BYTES) {
            return ['data' => $bytes, 'mime' => $mime];
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }
        $scale = min(1.0, self::MAX_SIDE / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        // A transparent image goes onto white: the result is a JPEG.
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        ob_start();
        imagejpeg($target, null, 82);
        $jpeg = (string) ob_get_clean();

        return ['data' => $jpeg, 'mime' => 'image/jpeg'];
    }
}
