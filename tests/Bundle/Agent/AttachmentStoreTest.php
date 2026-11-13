<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Agent;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentRejectedException;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentStore;
use PHPUnit\Framework\TestCase;

final class AttachmentStoreTest extends TestCase
{
    private Connection $connection;
    private AttachmentStore $store;
    private string $directory;
    /** @var list<string> */
    private array $temp = [];

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE app_ai_attachment (
            id CHAR(36) PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(160) NOT NULL, mime VARCHAR(40) NOT NULL, size INT NOT NULL,
            width INT NOT NULL, height INT NOT NULL, filename VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL)');
        $this->directory = sys_get_temp_dir() . '/ai-attachments-test-' . bin2hex(random_bytes(4));
        $this->store = new AttachmentStore($this->connection, $this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
        foreach ($this->temp as $file) {
            @unlink($file);
        }
    }

    private function image(int $width = 40, int $height = 30, string $format = 'png'): string
    {
        $image = imagecreatetruecolor($width, $height);
        $path = tempnam(sys_get_temp_dir(), 'img') ?: '';
        $this->temp[] = $path;
        match ($format) {
            'jpeg' => imagejpeg($image, $path),
            'gif' => imagegif($image, $path),
            default => imagepng($image, $path),
        };

        return $path;
    }

    private function file(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'att') ?: '';
        $this->temp[] = $path;
        file_put_contents($path, $content);

        return $path;
    }

    public function testARealImageIsKeptWithItsOwnTypeAndSize(): void
    {
        // The name says jpg, the file is a PNG: the file decides.
        $attachment = $this->store->store($this->image(40, 30), 'photo.jpg', 14);

        self::assertSame('image/png', $attachment->mime);
        self::assertSame([40, 30], [$attachment->width, $attachment->height]);
        self::assertStringEndsWith('.png', $attachment->filename);
        self::assertFileExists($this->store->path($attachment));
        self::assertTrue(AttachmentStore::isValidId($attachment->id));
    }

    public function testWhatIsNotAnImageIsRefusedWhateverItIsCalled(): void
    {
        $this->expectException(AttachmentRejectedException::class);
        $this->expectExceptionMessage('Only JPEG, PNG, GIF and WebP');

        $this->store->store($this->file('<?php echo "hi";'), 'photo.png', 14);
    }

    public function testAnEmptyFileAndATooHeavyOneAreRefused(): void
    {
        try {
            $this->store->store($this->file(''), 'a.png', 14);
            self::fail('empty');
        } catch (AttachmentRejectedException $e) {
            self::assertSame('The file is empty.', $e->getMessage());
        }

        $this->expectException(AttachmentRejectedException::class);
        $this->expectExceptionMessage('too large');
        $heavy = $this->image();
        file_put_contents($heavy, str_repeat('0', AttachmentStore::MAX_BYTES), FILE_APPEND);
        $this->store->store($heavy, 'big.png', 14);
    }

    public function testAnImageWithTooManyPixelsIsRefused(): void
    {
        $this->expectException(AttachmentRejectedException::class);
        $this->expectExceptionMessage('too many pixels');

        $this->store->store($this->image(AttachmentStore::MAX_SIDE + 1, 1), 'wide.png', 14);
    }

    public function testAnAttachmentIsOnlyFoundByItsOwner(): void
    {
        $attachment = $this->store->store($this->image(), 'a.png', 14);

        self::assertNotNull($this->store->find($attachment->id, 14));
        self::assertNull($this->store->find($attachment->id, 15));
        self::assertNull($this->store->find('00000000-0000-4000-8000-000000000000', 14));
    }

    public function testOldAttachmentsAreRemovedWhenANewOneComes(): void
    {
        $old = $this->store->store($this->image(), 'old.png', 14, new DateTimeImmutable('2026-11-01 10:00:00 UTC'));
        $oldPath = $this->store->path($old);
        self::assertFileExists($oldPath);

        $this->store->store($this->image(), 'new.png', 14, new DateTimeImmutable('2026-11-13 10:00:00 UTC'));

        self::assertNull($this->store->find($old->id, 14));
        self::assertFileDoesNotExist($oldPath);
    }

    public function testNamesAreCleanedAndIdsCheckedBeforeAnyLookup(): void
    {
        self::assertSame('a b .png', AttachmentStore::cleanName("../../a\"b<.png"));
        self::assertSame('image', AttachmentStore::cleanName(" \n"));
        self::assertFalse(AttachmentStore::isValidId('../../etc/passwd'));
        self::assertFalse(AttachmentStore::isValidId("1' OR '1'='1"));
        self::assertFalse(AttachmentStore::isValidId(null));
    }
}
