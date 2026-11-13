<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Agent;

use Doctrine\DBAL\DriverManager;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentStore;
use Masilia\Bundle\IbexaAi\Agent\Attachment\UserMessageBuilder;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use PHPUnit\Framework\TestCase;

final class UserMessageBuilderTest extends TestCase
{
    private AttachmentStore $store;
    private UserMessageBuilder $builder;
    private string $directory;
    /** @var list<string> */
    private array $temp = [];

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE app_ai_attachment (
            id CHAR(36) PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(160) NOT NULL, mime VARCHAR(40) NOT NULL, size INT NOT NULL,
            width INT NOT NULL, height INT NOT NULL, filename VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL)');
        $this->directory = sys_get_temp_dir() . '/ai-message-test-' . bin2hex(random_bytes(4));
        $this->store = new AttachmentStore($connection, $this->directory);
        $this->builder = new UserMessageBuilder($this->store);
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

    private function attach(int $userId, int $width = 80, int $height = 60): string
    {
        $path = tempnam(sys_get_temp_dir(), 'img') ?: '';
        $this->temp[] = $path;
        imagepng(imagecreatetruecolor($width, $height), $path);

        return $this->store->store($path, 'photo.png', $userId)->id;
    }

    private function tag(string $id): string
    {
        return sprintf('<attachment-image name="photo.png" id="%s" width="80" height="60" bytes="100"/>', $id);
    }

    /**
     * @return list<ImageContent>
     */
    private function images(string $text, bool $vision, int $userId): array
    {
        return array_values(array_filter(
            $this->builder->build($text, $vision, $userId)->getContentBlocks(),
            static fn ($block): bool => $block instanceof ImageContent,
        ));
    }

    public function testAModelThatReadsImagesGetsTheImageWithTheMessage(): void
    {
        $id = $this->attach(14);
        $images = $this->images("What is this?\n\n" . $this->tag($id), true, 14);

        self::assertCount(1, $images);
        self::assertSame('image/png', $images[0]->mediaType);
        self::assertNotFalse(getimagesizefromstring((string) base64_decode($images[0]->content, true)));
    }

    public function testTheTextKeepsTheTagWhetherOrNotTheImageGoes(): void
    {
        $text = "What is this?\n\n" . $this->tag($this->attach(14));

        self::assertSame($text, $this->builder->build($text, true, 14)->getContent());
        self::assertSame($text, $this->builder->build($text, false, 14)->getContent());
    }

    public function testAModelThatCannotReadImagesGetsNoImage(): void
    {
        self::assertSame([], $this->images($this->tag($this->attach(14)), false, 14));
    }

    public function testAnotherUsersImageIsNotSent(): void
    {
        $id = $this->attach(15);

        self::assertSame([], $this->images($this->tag($id), true, 14));
    }

    public function testAnUnknownIdIsNotAnImage(): void
    {
        self::assertSame([], $this->images($this->tag('00000000-0000-4000-8000-000000000000'), true, 14));
    }

    public function testNoMoreThanTheLimitGoToTheModel(): void
    {
        $text = 'Look';
        for ($i = 0; $i < 5; ++$i) {
            $text .= "\n" . $this->tag($this->attach(14));
        }

        self::assertCount(3, $this->images($text, true, 14));
    }

    public function testALargeImageIsReducedBeforeItGoesToTheModel(): void
    {
        $id = $this->attach(14, 3000, 1500);
        $images = $this->images($this->tag($id), true, 14);

        self::assertSame('image/jpeg', $images[0]->mediaType);
        $size = getimagesizefromstring((string) base64_decode($images[0]->content, true));
        self::assertSame(1568, $size[0]);
    }
}
