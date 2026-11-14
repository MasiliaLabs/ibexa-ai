<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Provider;

use NeuronAI\Providers\AIProviderInterface;

/** The chat model a siteaccess uses: the Neuron provider to talk to it, and what is told of it (usage reports, vision). */
final readonly class ChatConnection
{
    public function __construct(
        public AIProviderInterface $provider,
        /** The identifier of the provider as the admin shows it ("openai", "anthropic"…) */
        public string $providerIdentifier,
        public string $model,
        /** Whether the model reads the images that come with a message */
        public bool $supportsVision,
    ) {
    }
}
