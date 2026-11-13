<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\DTO;

/**
 * One conversation of a user with the agent, as the history lists it.
 */
final readonly class AgentThread
{
    public function __construct(
        public string $id,
        public int $userId,
        public string $siteaccess,
        public string $title,
        public string $updatedAt,
    ) {
    }

    /**
     * @return array{id: string, title: string, updatedAt: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'updatedAt' => $this->updatedAt];
    }
}
