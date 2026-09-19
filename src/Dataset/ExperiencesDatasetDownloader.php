<?php

namespace App\Dataset;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads "Liste des expériences partagées par les usagers", the open dataset
 * of the French public services feedback platform, from data.gouv.fr.
 *
 * @see https://www.data.gouv.fr/datasets/liste-des-experiences-partagees-par-les-usagers
 */
final class ExperiencesDatasetDownloader
{
    private const DATASET_URL = 'https://www.data.gouv.fr/api/1/datasets/liste-des-experiences-partagees-par-les-usagers/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(param: 'app.experiences_dataset_path')] private readonly string $path,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param (\Closure(int $downloadedBytes, int $totalBytes): void)|null $onProgress
     */
    public function download(bool $force = false, ?\Closure $onProgress = null): DatasetFile
    {
        if (!$force && is_file($this->path)) {
            return new DatasetFile($this->path, (int) filesize($this->path), false);
        }

        // The file is republished every day under a new URL, only the dataset API knows the current one
        $resource = $this->findCsvResource();

        $response = $this->httpClient->request('GET', $resource['url'], ['buffer' => false]);
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(\sprintf('Downloading "%s" failed with status code %d.', $resource['url'], $response->getStatusCode()));
        }

        $total = (int) ($response->getHeaders()['content-length'][0] ?? $resource['filesize'] ?? 0);

        // Written next to the target and renamed once complete, so a failed download never leaves a truncated dataset
        $this->filesystem->mkdir(\dirname($this->path));
        $temporaryPath = $this->filesystem->tempnam(\dirname($this->path), 'experiences_', '.part');

        try {
            $handle = fopen($temporaryPath, 'w');
            $downloaded = 0;
            foreach ($this->httpClient->stream($response) as $chunk) {
                $downloaded += fwrite($handle, $chunk->getContent());
                $onProgress?->__invoke($downloaded, $total);
            }
            fclose($handle);

            $this->filesystem->rename($temporaryPath, $this->path, true);
        } catch (\Throwable $e) {
            $this->filesystem->remove($temporaryPath);

            throw $e;
        }

        return new DatasetFile(
            $this->path,
            $downloaded,
            true,
            isset($resource['last_modified']) ? new \DateTimeImmutable($resource['last_modified']) : null,
        );
    }

    /**
     * @return array{url: string, filesize?: int, last_modified?: string}
     */
    private function findCsvResource(): array
    {
        $dataset = $this->httpClient->request('GET', self::DATASET_URL)->toArray();

        foreach ($dataset['resources'] ?? [] as $resource) {
            if ('csv' === strtolower($resource['format'] ?? '') && isset($resource['url'])) {
                return $resource;
            }
        }

        throw new \RuntimeException('The dataset does not expose a CSV file anymore.');
    }
}
