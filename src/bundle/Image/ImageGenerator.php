<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Image;

use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\IbexaAi\Repository\AiProviderRepository;
use Masilia\Bundle\IbexaAi\Support\ImageGenerationResult;
use Masilia\Bundle\IbexaAi\Support\ImageGeneratorInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Masilia\Bundle\IbexaAi\Security\ProviderKeys;

/**
 * Image generation for the current siteaccess, using the active image model of the admin.
 *
 * Two API shapes are supported, as in the previous package:
 *  - OpenAI images (/images/generations), also used by OpenAI-compatible gateways;
 *  - MiniMax image generation (/v1/image_generation).
 */
final readonly class ImageGenerator implements ImageGeneratorInterface
{
    private const OPENAI_BASE = 'https://api.openai.com/v1';
    private const MINIMAX_HOST = 'https://api.minimax.io';
    private const MINIMAX_RATIOS = ['1:1', '16:9', '4:3', '3:2', '2:3', '3:4', '9:16', '21:9'];

    public function __construct(
        private AiProviderRepository $providers,
        private SiteAccessServiceInterface $siteAccess,
        private HttpClientInterface $http,
        private ProviderKeys $keys,
    ) {
    }

    public function isConfigured(): bool
    {
        $provider = $this->target();

        return $provider !== null && $this->keys->has($provider);
    }

    public function generate(string $prompt, ?string $size = null, ?string $quality = null): ImageGenerationResult
    {
        $provider = $this->target() ?? throw new RuntimeException('No image model is configured for this siteaccess.');
        $model = $provider->getActiveImageModel()?->getIdentifier() ?? throw new RuntimeException('No image model is selected.');
        $key = $this->keys->reveal($provider) ?? throw new RuntimeException('The image provider has no API key.');

        return match ($provider->getIdentifier()) {
            'openai' => $this->openAi($provider->getApiUrl(), $key, $model, $prompt, $size, $quality),
            'minimax' => $this->minimax($provider->getApiUrl(), $key, $model, $prompt, $size),
            default => throw new RuntimeException(sprintf('Image generation is not supported for "%s".', $provider->getIdentifier())),
        };
    }

    private function target(): ?\Masilia\Bundle\IbexaAi\Entity\AiProvider
    {
        return $this->providers->findActiveImageForSiteaccess($this->currentSiteaccess());
    }

    private function openAi(?string $apiUrl, string $key, string $model, string $prompt, ?string $size, ?string $quality): ImageGenerationResult
    {
        $body = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $this->openAiSize($size),
        ];
        if ($quality !== null) {
            $body['quality'] = $quality;
        }

        $data = $this->post(rtrim($apiUrl ?: self::OPENAI_BASE, '/') . '/images/generations', $key, $body);
        $item = $data['data'][0] ?? [];

        if (isset($item['b64_json'])) {
            return new ImageGenerationResult((string) $item['b64_json'], 'image/png', $item['revised_prompt'] ?? null);
        }

        if (isset($item['url'])) {
            return $this->downloaded((string) $item['url'], $item['revised_prompt'] ?? null);
        }

        throw new RuntimeException('The image API returned no image.');
    }

    private function minimax(?string $apiUrl, string $key, string $model, string $prompt, ?string $size): ImageGenerationResult
    {
        // The stored URL may be the host ("https://api.minimax.io") or the "/v1" base: the image path is "/v1/image_generation".
        $host = preg_replace('#/v1/?$#', '', rtrim($apiUrl ?: self::MINIMAX_HOST, '/')) ?? self::MINIMAX_HOST;

        $data = $this->post($host . '/v1/image_generation', $key, [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'response_format' => 'base64',
            'aspect_ratio' => $this->minimaxRatio($size),
        ]);

        $status = $data['base_resp']['status_code'] ?? 0;
        if ($status !== 0) {
            throw new RuntimeException(sprintf('MiniMax image API error %d: %s', $status, $data['base_resp']['status_msg'] ?? 'unknown error'));
        }

        if (!empty($data['data']['image_base64'][0])) {
            return new ImageGenerationResult((string) $data['data']['image_base64'][0], 'image/png');
        }

        if (!empty($data['data']['image_urls'][0])) {
            return $this->downloaded((string) $data['data']['image_urls'][0]);
        }

        throw new RuntimeException('MiniMax image API returned no image data.');
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function post(string $url, string $key, array $body): array
    {
        $response = $this->http->request('POST', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $key],
            'json' => $body,
            'timeout' => 120,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('The image API answered HTTP %d.', $response->getStatusCode()));
        }

        return $response->toArray(false);
    }

    private function downloaded(string $url, ?string $revisedPrompt = null): ImageGenerationResult
    {
        $response = $this->http->request('GET', $url, ['timeout' => 120]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('The generated image could not be downloaded.');
        }

        $mime = $response->getHeaders(false)['content-type'][0] ?? 'image/png';

        return new ImageGenerationResult(base64_encode($response->getContent()), explode(';', $mime)[0], $revisedPrompt);
    }

    /**
     * Pixel sizes pass through; aspect ratios map to the closest supported pixel size.
     */
    private function openAiSize(?string $size): string
    {
        if ($size !== null && preg_match('/^\d+x\d+$/', $size) === 1) {
            return $size;
        }

        return match ($size) {
            '16:9', '3:2', '4:3' => '1536x1024',
            '9:16', '2:3', '3:4' => '1024x1536',
            default => '1024x1024',
        };
    }

    private function minimaxRatio(?string $size): string
    {
        if ($size !== null && in_array($size, self::MINIMAX_RATIOS, true)) {
            return $size;
        }

        if ($size !== null && preg_match('/^(\d+)x(\d+)$/', $size, $m) === 1 && (int) $m[2] > 0) {
            $ratio = (int) $m[1] / (int) $m[2];
            $closest = '1:1';
            $best = PHP_FLOAT_MAX;
            foreach (self::MINIMAX_RATIOS as $candidate) {
                [$w, $h] = array_map('intval', explode(':', $candidate));
                $distance = abs($ratio - $w / $h);
                if ($distance < $best) {
                    $best = $distance;
                    $closest = $candidate;
                }
            }

            return $closest;
        }

        return '1:1';
    }

    private function currentSiteaccess(): string
    {
        return $this->siteAccess->getCurrent()?->name ?? 'default';
    }
}
