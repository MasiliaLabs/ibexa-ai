<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A model offered by a provider (table app_ai_model).
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_ai_model')]
class AiModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AiProvider::class, inversedBy: 'models')]
    #[ORM\JoinColumn(name: 'provider_id', nullable: false, onDelete: 'CASCADE')]
    private AiProvider $provider;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 100)]
    private string $identifier = '';

    #[ORM\Column(name: 'supports_image', type: 'boolean')]
    private bool $supportsImage = false;

    #[ORM\Column(name: 'supports_vision', type: 'boolean', options: ['default' => false])]
    private bool $supportsVision = false;

    #[ORM\Column(type: 'float')]
    private float $temperature = 0.7;

    #[ORM\Column(name: 'max_tokens', type: 'integer')]
    private int $maxTokens = 4096;

    public function __construct(AiProvider $provider)
    {
        $this->provider = $provider;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): AiProvider
    {
        return $this->provider;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /** Whether the model can read images sent to it (not generate them: see supportsImage). */
    public function supportsVision(): bool
    {
        return $this->supportsVision;
    }

    public function setSupportsVision(bool $supportsVision): void
    {
        $this->supportsVision = $supportsVision;
    }

    public function supportsImage(): bool
    {
        return $this->supportsImage;
    }

    public function setSupportsImage(bool $supportsImage): void
    {
        $this->supportsImage = $supportsImage;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): void
    {
        $this->temperature = $temperature;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->maxTokens = $maxTokens;
    }
}
