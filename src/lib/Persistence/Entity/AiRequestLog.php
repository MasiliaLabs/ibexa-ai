<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Persistence\Entity;

use Doctrine\ORM\Mapping as ORM;
use Masilia\IbexaAi\Persistence\Repository\AiRequestLogRepository;
use Masilia\Contracts\IbexaAi\Observability\RequestLog;

/**
 * One model call (table app_ai_request_log, shared with the previous package).
 */
#[ORM\Entity(repositoryClass: AiRequestLogRepository::class)]
#[ORM\Table(name: 'app_ai_request_log')]
#[ORM\Index(name: 'idx_ai_log_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_ai_log_provider', columns: ['provider_identifier'])]
class AiRequestLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'provider_identifier', length: 32)]
    private string $providerIdentifier;

    #[ORM\Column(name: 'model_identifier', length: 100)]
    private string $modelIdentifier;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $siteaccess = null;

    #[ORM\Column(type: 'boolean')]
    private bool $success;

    #[ORM\Column(name: 'latency_ms', type: 'integer')]
    private int $latencyMs;

    #[ORM\Column(name: 'error_code', length: 64, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(name: 'tokens_in', type: 'integer', nullable: true)]
    private ?int $tokensIn = null;

    #[ORM\Column(name: 'tokens_out', type: 'integer', nullable: true)]
    private ?int $tokensOut = null;

    #[ORM\Column(name: 'finish_reason', length: 32, nullable: true)]
    private ?string $finishReason = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public static function fromLog(RequestLog $log): self
    {
        $entity = new self();
        $entity->providerIdentifier = $log->providerIdentifier;
        $entity->modelIdentifier = $log->modelIdentifier;
        $entity->siteaccess = $log->siteaccess;
        $entity->success = $log->success;
        $entity->latencyMs = $log->latencyMs;
        $entity->errorCode = $log->errorCode;
        $entity->tokensIn = $log->tokensIn;
        $entity->tokensOut = $log->tokensOut;
        $entity->finishReason = $log->finishReason;
        $entity->createdAt = $log->createdAt;

        return $entity;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProviderIdentifier(): string
    {
        return $this->providerIdentifier;
    }

    public function getModelIdentifier(): string
    {
        return $this->modelIdentifier;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getLatencyMs(): int
    {
        return $this->latencyMs;
    }

    public function getTokensIn(): ?int
    {
        return $this->tokensIn;
    }

    public function getTokensOut(): ?int
    {
        return $this->tokensOut;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
