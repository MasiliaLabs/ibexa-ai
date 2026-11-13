<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Agent;

use Masilia\Bundle\IbexaAi\Agent\Attachment\ImageForModel;
use Masilia\Bundle\IbexaAi\Agent\Attachment\ImageTags;
use PHPUnit\Framework\TestCase;

final class ImageForModelTest extends TestCase
{
    private function png(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'img') ?: '';
        imagepng(imagecreatetruecolor($width, $height), $path);

        return $path;
    }

    public function testASmallImageIsSentAsItIs(): void
    {
        $path = $this->png(200, 100);
        $image = ImageForModel::prepare($path, 'image/png', 200, 100);

        self::assertSame('image/png', $image['mime'] ?? null);
        self::assertSame(file_get_contents($path), $image['data'] ?? null);
        unlink($path);
    }

    public function testALargeImageIsReducedToWhatAModelReads(): void
    {
        $path = $this->png(3200, 1600);
        $image = ImageForModel::prepare($path, 'image/png', 3200, 1600);
        unlink($path);

        self::assertNotNull($image);
        self::assertSame('image/jpeg', $image['mime']);
        $size = getimagesizefromstring($image['data']);
        self::assertSame([ImageForModel::MAX_SIDE, 784], [$size[0], $size[1]]);
    }

    public function testAFileThatIsNotAnImageGivesNothing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x') ?: '';
        file_put_contents($path, 'not an image');

        self::assertNull(ImageForModel::prepare($path, 'image/png', 4000, 4000));
        self::assertNull(ImageForModel::prepare('/does/not/exist.png', 'image/png', 10, 10));
        unlink($path);
    }

    public function testTheImagesAMessageNamesAreItsTagsOnly(): void
    {
        $a = '3f2b8c1e-5d6a-4e7f-9a10-2b3c4d5e6f70';
        $b = '11111111-2222-4333-8444-555555555555';
        $text = "Look\n\n<attachment-image name=\"a.png\" id=\"$a\" width=\"10\" height=\"10\" bytes=\"5\"/>\n<attachment-image name=\"b.png\" id=\"$b\" width=\"1\" height=\"1\" bytes=\"2\"/>\n<attachment-image name=\"again.png\" id=\"$a\" width=\"10\" height=\"10\" bytes=\"5\"/>";

        self::assertSame([$a, $b], ImageTags::ids($text));
        self::assertSame([], ImageTags::ids('<attachment-image name="x" id="../../etc/passwd" width="1" height="1" bytes="1"/>'));
        self::assertSame([], ImageTags::ids('No image here'));
    }
}
