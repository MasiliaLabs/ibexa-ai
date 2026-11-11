<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Masilia\Bundle\IbexaAi\Repository\AiProviderRepository;

/**
 * A configured AI provider account (table app_ai_provider, shared with the previous package).
 */
#[ORM\Entity(repositoryClass: AiProviderRepository::class)]
#[ORM\Table(name: 'app_ai_provider')]
class AiProvider
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 100)]
    private string $identifier = '';

    #[ORM\Column(name: 'api_key', length: 255, nullable: true)]
    private ?string $apiKey = null;

    #[ORM\Column(name: 'api_url', length: 255, nullable: true)]
    private ?string $apiUrl = null;

    #[ORM\ManyToOne(targetEntity: AiModel::class)]
    #[ORM\JoinColumn(name: 'active_chat_model_id', nullable: true, onDelete: 'SET NULL')]
    private ?AiModel $activeChatModel = null;

    #[ORM\ManyToOne(targetEntity: AiModel::class)]
    #[ORM\JoinColumn(name: 'active_image_model_id', nullable: true, onDelete: 'SET NULL')]
    private ?AiModel $activeImageModel = null;

    /** @var Collection<int, AiModel> */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: AiModel::class, cascade: ['remove'])]
    private Collection $models;

    /** @var Collection<int, AiProviderSiteaccess> */
    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: AiProviderSiteaccess::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $siteaccesses;

    public function __construct()
    {
        $this->models = new ArrayCollection();
        $this->siteaccesses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getApiUrl(): ?string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(?string $apiUrl): void
    {
        $this->apiUrl = $apiUrl;
    }

    public function getActiveChatModel(): ?AiModel
    {
        return $this->activeChatModel;
    }

    public function setActiveChatModel(?AiModel $model): void
    {
        $this->activeChatModel = $model;
    }

    public function getActiveImageModel(): ?AiModel
    {
        return $this->activeImageModel;
    }

    public function setActiveImageModel(?AiModel $model): void
    {
        $this->activeImageModel = $model;
    }

    /** @return Collection<int, AiModel> */
    public function getModels(): Collection
    {
        return $this->models;
    }

    /** @return Collection<int, AiProviderSiteaccess> */
    public function getSiteaccesses(): Collection
    {
        return $this->siteaccesses;
    }
}
