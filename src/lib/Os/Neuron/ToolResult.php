<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Neuron;

use NeuronAI\Tools\ToolOutput;

readonly class ToolResult
{
    public function __construct(
        public bool   $success,
        public string $message = '',
        public array  $data = [],
    ) {
    }

    public static function ok(string $message = '', array $data = []): self
    {
        return new self(success: true, message: $message, data: $data);
    }

    public static function error(string $message): self
    {
        return new self(success: false, message: $message);
    }

    /**
     * What the agent records as the answer of a tool: the JSON the chat reads ({success, message, data}), and, for a failure, Neuron's own
     * error flag, so that the providers that have one (Anthropic's is_error) and the stored conversation know it failed.
     */
    public function toOutput(): ToolOutput
    {
        $json = json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $this->success ? ToolOutput::text($json) : ToolOutput::error($json);
    }

    /**
     * @return array{success: bool, message: string, data: array}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
