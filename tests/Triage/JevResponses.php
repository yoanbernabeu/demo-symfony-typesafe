<?php

namespace App\Tests\Triage;

use Symfony\AI\Platform\Bridge\TypeSafe\Factory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * A Jev that answers what tests tell it to, without any network call nor API key.
 */
final class JevResponses
{
    /** @var list<array<string, mixed>> The JSON bodies received, in order */
    public array $requests = [];

    /**
     * @param \Closure(string $state): MockResponse $respond
     */
    public function __construct(
        private readonly \Closure $respond,
    ) {
    }

    public function createPlatform(): PlatformInterface
    {
        return Factory::createPlatform('test-key', new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);

            return ($this->respond)($body['state']);
        }));
    }

    public static function answer(string $intention, float $confidence, float $priority, float $bug, int $inputTokens = 400, int $outputTokens = 70): MockResponse
    {
        return new JsonMockResponse([
            'model' => 'jev-1.13.0',
            'answers' => [
                'intention' => ['type' => 'choice', 'choice' => $intention, 'probabilities' => [$intention => $confidence], 'confidence' => $confidence],
                'priority' => ['type' => 'score', 'score' => $priority, 'legend' => ['a', 'b', 'c', 'd'], 'probabilities' => [0.1, 0.2, 0.3, 0.4], 'confidence' => 0.9],
                'bug' => ['type' => 'noul', 'noul' => $bug],
            ],
            'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
        ]);
    }
}
