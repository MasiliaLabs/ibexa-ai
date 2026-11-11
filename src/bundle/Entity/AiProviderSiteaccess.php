<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Links a provider to a siteaccess (table app_ai_provider_siteaccess, composite key).
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_ai_provider_siteaccess')]
class AiProviderSiteaccess
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: AiProvider::class, inversedBy: 'siteaccesses')]
    #[ORM\JoinColumn(name: 'provider_id', nullable: false, onDelete: 'CASCADE')]
    private AiProvider $provider;

    #[ORM\Id]
    #[ORM\Column(length: 100)]
    private string $siteaccess;

    public function __construct(AiProvider $provider, string $siteaccess)
    {
        $this->provider = $provider;
        $this->siteaccess = $siteaccess;
    }

    public function getProvider(): AiProvider
    {
        return $this->provider;
    }

    public function getSiteaccess(): string
    {
        return $this->siteaccess;
    }
}
