<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Tool;

/**
 * Index of the assistant's tools by name.
 *
 * Built on first use, not in the constructor: some tools (for example explore_site) depend on
 * this registry to call other tools, so the tools must not be instantiated while it is being built.
 */
final class ToolRegistry
{
    /** @var array<string, ToolInterface>|null */
    private ?array $tools = null;

    /**
     * @param iterable<ToolInterface> $tools
     */
    public function __construct(
        private readonly iterable $sources = [],
    ) {
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->index()[$name] ?? null;
    }

    /**
     * @return array<string, ToolInterface>
     */
    public function getAll(): array
    {
        return $this->index();
    }

    /**
     * Get JSON schemas for all tools (for LLM function calling).
     *
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getSchemas(): array
    {
        $schemas = [];
        foreach ($this->index() as $tool) {
            $schemas[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameters(),
            ];
        }

        return $schemas;
    }

    /**
     * @return array<string, ToolInterface>
     */
    private function index(): array
    {
        if ($this->tools === null) {
            $indexed = [];
            foreach ($this->sources as $tool) {
                $indexed[$tool->getName()] = $tool;
            }
            $this->tools = $indexed;
        }

        return $this->tools;
    }
}
