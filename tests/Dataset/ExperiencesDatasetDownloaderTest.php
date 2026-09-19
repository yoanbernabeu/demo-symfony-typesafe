<?php

namespace App\Tests\Dataset;

use App\Dataset\ExperiencesDatasetDownloader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExperiencesDatasetDownloaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/demojev_'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testItDownloadsTheCsvAdvertisedByTheDatasetApi(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;

            return 1 === \count($requestedUrls)
                ? new JsonMockResponse(['resources' => [
                    ['format' => 'ods', 'url' => 'https://static.example/export.ods'],
                    ['format' => 'csv', 'url' => 'https://static.example/export.csv', 'filesize' => 12, 'last_modified' => '2026-09-19T06:00:06+00:00'],
                ]])
                : new MockResponse(['"ID";"Desc', 'ription"'."\n"]);
        });
        $progress = [];

        $file = (new ExperiencesDatasetDownloader($httpClient, $this->directory.'/experiences.csv'))
            ->download(onProgress: static function (int $downloaded, int $total) use (&$progress): void {
                $progress[] = $downloaded;
            });

        self::assertSame('https://static.example/export.csv', $requestedUrls[1]);
        self::assertTrue($file->downloaded);
        self::assertSame('"ID";"Description"'."\n", file_get_contents($file->path));
        self::assertSame(\strlen('"ID";"Description"'."\n"), $file->size);
        self::assertSame('2026-09-19', $file->publishedAt?->format('Y-m-d'));
        self::assertSame($file->size, end($progress));
        self::assertSame(['experiences.csv'], array_values(array_diff(scandir($this->directory), ['.', '..'])), 'No partial file is left behind.');
    }

    public function testItKeepsAnExistingDatasetUnlessForced(): void
    {
        (new Filesystem())->dumpFile($this->directory.'/experiences.csv', 'old');
        $httpClient = new MockHttpClient(static fn (string $method, string $url): MockResponse => str_contains($url, 'data.gouv.fr')
            ? new JsonMockResponse(['resources' => [['format' => 'CSV', 'url' => 'https://static.example/export.csv']]])
            : new MockResponse('new'));
        $downloader = new ExperiencesDatasetDownloader($httpClient, $this->directory.'/experiences.csv');

        $kept = $downloader->download();

        self::assertFalse($kept->downloaded);
        self::assertSame(0, $httpClient->getRequestsCount());
        self::assertSame('old', file_get_contents($kept->path));

        $forced = $downloader->download(force: true);

        self::assertTrue($forced->downloaded);
        self::assertSame('new', file_get_contents($forced->path));
    }

    public function testAFailedDownloadLeavesNothingBehind(): void
    {
        $httpClient = new MockHttpClient(static fn (string $method, string $url): MockResponse => str_contains($url, 'data.gouv.fr')
            ? new JsonMockResponse(['resources' => [['format' => 'csv', 'url' => 'https://static.example/export.csv']]])
            : new MockResponse('', ['http_code' => 503]));

        try {
            (new ExperiencesDatasetDownloader($httpClient, $this->directory.'/experiences.csv'))->download();
            self::fail('A failed download must throw.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('503', $e->getMessage());
        }

        self::assertFileDoesNotExist($this->directory.'/experiences.csv');
    }

    public function testItFailsWhenTheDatasetHasNoCsv(): void
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['resources' => [['format' => 'ods', 'url' => 'https://static.example/export.ods']]]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CSV');

        (new ExperiencesDatasetDownloader($httpClient, $this->directory.'/experiences.csv'))->download();
    }
}
