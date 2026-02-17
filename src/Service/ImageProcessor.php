<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageProcessor
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $targetDirectory,
        private readonly string $authHeaderName = 'x-api-key',
        private readonly string $authHeaderPrefix = '',
    ) {
    }

    public function processAndStore(UploadedFile $uploadedFile, string $baseFilename): string
    {
        if (!is_dir($this->targetDirectory) && !mkdir($concurrentDirectory = $this->targetDirectory, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create upload directory "%s".', $this->targetDirectory));
        }

        $processedImage = $this->requestProcessedImage($uploadedFile);

        $extension = $this->extensionFromMimeType($processedImage['mimeType'])
            ?? $uploadedFile->guessExtension()
            ?? $uploadedFile->getClientOriginalExtension()
            ?? 'jpg';

        $filename = sprintf('%s-%s.%s', $baseFilename, uniqid(), $extension);
        $destination = rtrim($this->targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($destination, $processedImage['content']) === false) {
            throw new \RuntimeException('Unable to save processed image to disk.');
        }

        return $filename;
    }

    public function deleteIfExists(?string $filename): void
    {
        if (!$filename) {
            return;
        }

        $path = rtrim($this->targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function requestProcessedImage(UploadedFile $uploadedFile): array
    {
        if ($this->apiUrl === '') {
            throw new \RuntimeException('IMAGE_PROCESSOR_API_URL is empty.');
        }
        if ($this->apiKey === '') {
            throw new \RuntimeException('IMAGE_PROCESSOR_API_KEY is empty.');
        }

        $headers = [sprintf(
            '%s: %s%s',
            $this->authHeaderName,
            $this->authHeaderPrefix,
            $this->apiKey
        )];

        $response = $this->httpClient->request('POST', $this->apiUrl, [
            'headers' => $headers,
            'body' => [
                'image_file' => fopen($uploadedFile->getPathname(), 'r'),
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $responseHeaders = $response->getHeaders(false);
        $contentTypeHeader = $responseHeaders['content-type'][0] ?? '';
        $mimeType = $this->normalizeMimeType($contentTypeHeader);
        $rawBody = $response->getContent(false);

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->error('Image API request failed.', [
                'status' => $statusCode,
                'content_type' => $contentTypeHeader,
                'response_preview' => mb_substr($rawBody, 0, 1000),
                'url' => $this->apiUrl,
            ]);
            throw new \RuntimeException(sprintf('Image API failed with status %d.', $statusCode));
        }

        if (str_starts_with($mimeType, 'image/')) {
            return [
                'content' => $rawBody,
                'mimeType' => $mimeType,
            ];
        }

        if ($mimeType === 'application/octet-stream') {
            return [
                'content' => $rawBody,
                'mimeType' => $uploadedFile->getMimeType() ?: null,
            ];
        }

        $isJson = str_contains($mimeType, 'json') || $this->looksLikeJson($rawBody);
        if ($isJson) {
            try {
                /** @var array<string, mixed> $data */
                $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->error('Image API returned invalid JSON.', [
                    'content_type' => $contentTypeHeader,
                    'response_preview' => mb_substr($rawBody, 0, 1000),
                    'url' => $this->apiUrl,
                ]);
                throw new \RuntimeException('Invalid JSON returned by image API.', 0, $e);
            }

            $base64Candidates = [
                $data['image_base64'] ?? null,
                $data['result_b64'] ?? null,
                $data['data']['image_base64'] ?? null,
                $data['data']['result_b64'] ?? null,
                $data['result']['b64_json'] ?? null,
            ];

            foreach ($base64Candidates as $base64Candidate) {
                if (!is_string($base64Candidate) || $base64Candidate === '') {
                    continue;
                }

                $decoded = base64_decode($base64Candidate, true);
                if ($decoded !== false) {
                    return [
                        'content' => $decoded,
                        'mimeType' => is_string($data['mime_type'] ?? null) ? $data['mime_type'] : null,
                    ];
                }
            }

            $urlCandidates = [
                $data['image_url'] ?? null,
                $data['result_url'] ?? null,
                $data['output_url'] ?? null,
                $data['url'] ?? null,
                $data['data']['image_url'] ?? null,
                $data['data']['url'] ?? null,
                $data['result']['url'] ?? null,
            ];

            foreach ($urlCandidates as $urlCandidate) {
                if (!is_string($urlCandidate) || $urlCandidate === '') {
                    continue;
                }

                $downloadResponse = $this->httpClient->request('GET', $urlCandidate);
                $downloadStatus = $downloadResponse->getStatusCode();
                $downloadHeaders = $downloadResponse->getHeaders(false);
                $downloadContentType = $downloadHeaders['content-type'][0] ?? '';
                $downloadMime = $this->normalizeMimeType($downloadContentType);
                $downloadBody = $downloadResponse->getContent(false);

                if ($downloadStatus >= 200 && $downloadStatus < 300) {
                    return [
                        'content' => $downloadBody,
                        'mimeType' => $downloadMime !== '' ? $downloadMime : null,
                    ];
                }

                $this->logger->warning('Image API returned a URL but download failed.', [
                    'download_status' => $downloadStatus,
                    'download_url' => $urlCandidate,
                    'download_content_type' => $downloadContentType,
                ]);
            }

            $this->logger->error('Unsupported image API JSON format.', [
                'json_keys' => array_keys($data),
                'response_preview' => mb_substr($rawBody, 0, 1000),
                'url' => $this->apiUrl,
            ]);
            throw new \RuntimeException('Unsupported image API response format.');
        }

        $this->logger->error('Unsupported non-JSON/non-image response from API.', [
            'content_type' => $contentTypeHeader,
            'response_preview' => mb_substr($rawBody, 0, 1000),
            'url' => $this->apiUrl,
        ]);
        throw new \RuntimeException('Unsupported image API response format.');
    }

    private function normalizeMimeType(?string $contentType): string
    {
        if ($contentType === null) {
            return '';
        }

        return strtolower(trim(explode(';', $contentType)[0]));
    }

    private function looksLikeJson(string $body): bool
    {
        $trimmedBody = ltrim($body);

        return $trimmedBody !== '' && ($trimmedBody[0] === '{' || $trimmedBody[0] === '[');
    }

    private function extensionFromMimeType(?string $mimeType): ?string
    {
        if ($mimeType === null) {
            return null;
        }

        return match (strtolower(trim(explode(';', $mimeType)[0]))) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };
    }
}
