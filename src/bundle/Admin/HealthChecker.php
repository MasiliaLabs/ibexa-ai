<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Admin;

use Masilia\Bundle\IbexaAi\Llm\ProviderResolver;
use Masilia\Bundle\IbexaAi\Repository\AiRequestLogRepository;
use Masilia\IbexaAi\Provider\ProviderConfigurationException;
use DateTimeImmutable;

/**
 * Banner state for a siteaccess, without calling the provider:
 *  - not_configured: no provider, model or key for this siteaccess;
 *  - offline: the last requests to this provider all failed;
 *  - online: otherwise.
 */
final readonly class HealthChecker
{
    private const WINDOW = 10;

    public function __construct(
        private ProviderResolver $resolver,
        private AiRequestLogRepository $logs,
    ) {
    }

    /**
     * @return array{state: string, providerName: string, message: string, checkedAt: string}
     */
    public function check(string $siteaccess): array
    {
        try {
            $connection = $this->resolver->resolveChat($siteaccess);
        } catch (ProviderConfigurationException $e) {
            return $this->report('not_configured', '', $e->getMessage());
        }

        $name = $connection->provider->label();
        if ($connection->provider->requiresApiKey() && $connection->apiKey === null) {
            return $this->report('not_configured', $name, 'API key missing.');
        }

        $outcomes = $this->logs->recentOutcomes($connection->provider->value, self::WINDOW);
        if ($outcomes !== [] && !in_array(true, $outcomes, true)) {
            return $this->report('offline', $name, sprintf('The last %d requests failed.', count($outcomes)));
        }

        return $this->report('online', $name, 'Ready.');
    }

    /**
     * @return array{state: string, providerName: string, message: string, checkedAt: string}
     */
    private function report(string $state, string $providerName, string $message): array
    {
        return [
            'state' => $state,
            'providerName' => $providerName,
            'message' => $message,
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
