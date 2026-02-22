<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PrescriptionMedicationImageService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $dailyMedBaseUrl = 'https://dailymed.nlm.nih.gov/dailymed/services',
    ) {
    }

    /**
     * @return array<int, array{name:string,image_url:string,fallback_image_url:string,source:string,has_real_image:bool,debug:string}>
     */
    public function resolveMedicationImages(string $rawMedications): array
    {
        $names = $this->extractMedicationNames($rawMedications);
        $items = [];
        foreach ($names as $name) {
            $fallbackImage = $this->buildFallbackSvgDataUri($name);
            $debug = [];
            $lookupNames = $this->buildLookupNames($name);

            // SerpAPI only: primary source for real medication images.
            $serpImage = null;
            foreach ($lookupNames as $lookupName) {
                $serpImage = $this->fetchSerpApiImage($lookupName, $debug);
                if ($serpImage !== null) {
                    break;
                }
            }
            if ($serpImage !== null) {
                $items[] = [
                    'name' => $name,
                    'image_url' => $serpImage,
                    'fallback_image_url' => $fallbackImage,
                    'source' => 'serpapi_image_search',
                    'has_real_image' => true,
                    'debug' => implode(' | ', $debug),
                ];
                continue;
            }

            $items[] = [
                'name' => $name,
                'image_url' => $fallbackImage,
                'fallback_image_url' => $fallbackImage,
                'source' => 'generated_fallback',
                'has_real_image' => false,
                'debug' => implode(' | ', $debug) . ' | fallback local actif',
            ];
        }

        return $items;
    }

    /**
     * @return array<int,string>
     */
    private function buildLookupNames(string $name): array
    {
        $normalized = mb_strtolower(trim($name));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        $aliases = [
            'algesic' => ['ibuprofen', 'paracetamol', 'analgesic'],
            'doliprane' => ['paracetamol', 'acetaminophen'],
            'dafalgan' => ['paracetamol', 'acetaminophen'],
            'advil' => ['ibuprofen'],
            'nurofen' => ['ibuprofen'],
            'augmentin' => ['amoxicillin'],
            'glucophage' => ['metformin'],
        ];

        $candidates = [$name];
        foreach ($aliases as $key => $values) {
            if (str_contains($normalized, $key)) {
                $candidates = array_merge($candidates, $values);
                break;
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $candidates))));
    }

    /**
     * @return array<int,string>
     */
    private function extractMedicationNames(string $rawMedications): array
    {
        $rawMedications = trim($rawMedications);
        if ($rawMedications === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,;|]+/u', $rawMedications) ?: [];
        $result = [];
        foreach ($parts as $part) {
            $clean = trim($part);
            if ($clean === '') {
                continue;
            }

            // Remove basic dosage hints to keep a cleaner name for lookup.
            $clean = preg_replace('/\b\d+([.,]\d+)?\s?(mg|g|ml|mcg|ui)\b/i', '', $clean) ?? $clean;
            $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);
            if ($clean !== '') {
                $result[] = $clean;
            }
        }

        return array_values(array_unique($result));
    }

    private function fetchDailyMedImage(string $drugName, array &$debug): ?string
    {
        $setId = $this->findSetIdByDrugName($drugName);
        if ($setId === null) {
            $debug[] = 'DailyMed: setid introuvable';
            return null;
        }

        $mediaEndpoint = rtrim($this->dailyMedBaseUrl, '/') . '/v2/spls/' . rawurlencode($setId) . '/media.json';

        try {
            $response = $this->httpClient->request('GET', $mediaEndpoint, [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 12,
            ]);

            if ($response->getStatusCode() >= 400) {
                $debug[] = 'DailyMed media HTTP ' . $response->getStatusCode();
                return null;
            }

            $data = $response->toArray(false);
            if (!is_array($data)) {
                return null;
            }

            $candidates = [];
            if (isset($data['data']) && is_array($data['data'])) {
                $candidates[] = $data['data'];
            }
            if (isset($data['media']) && is_array($data['media'])) {
                $candidates[] = $data['media'];
            }

            foreach ($candidates as $candidateList) {
                foreach ($candidateList as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $url = (string) ($item['url'] ?? $item['image'] ?? $item['link'] ?? '');
                    if ($url === '') {
                        continue;
                    }
                    if (!str_starts_with($url, 'http')) {
                        $url = 'https://dailymed.nlm.nih.gov/dailymed/' . ltrim($url, '/');
                    }
                    return $url;
                }
            }
        } catch (\Throwable $e) {
            $debug[] = 'DailyMed exception: ' . $e->getMessage();
            $this->logger->warning('DailyMed media fetch failed.', [
                'drug' => $drugName,
                'setid' => $setId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function fetchOpenverseImage(string $drugName, array &$debug): ?string
    {
        $endpoint = 'https://api.openverse.org/v1/images/';
        $queries = [
            trim($drugName) . ' medicine box',
            trim($drugName) . ' drug tablet',
            trim($drugName) . ' pharmaceutical package',
        ];

        foreach ($queries as $query) {
            try {
                $response = $this->httpClient->request('GET', $endpoint, [
                    'query' => [
                        'q' => $query,
                        'page_size' => 1,
                    ],
                    'headers' => ['Accept' => 'application/json'],
                    'timeout' => 10,
                ]);

                if ($response->getStatusCode() >= 400) {
                    $debug[] = 'Openverse HTTP ' . $response->getStatusCode();
                    continue;
                }

                $data = $response->toArray(false);
                if (!is_array($data) || !isset($data['results'][0]) || !is_array($data['results'][0])) {
                    $debug[] = 'Openverse: aucun resultat (' . $query . ')';
                    continue;
                }

                $item = $data['results'][0];
                $thumb = (string) ($item['thumbnail'] ?? '');
                if ($thumb !== '' && str_starts_with($thumb, 'http')) {
                    return $thumb;
                }
                $url = (string) ($item['url'] ?? '');
                if ($url !== '' && str_starts_with($url, 'http')) {
                    return $url;
                }
            } catch (\Throwable $e) {
                $debug[] = 'Openverse exception: ' . $e->getMessage();
                $this->logger->warning('Openverse image search failed.', [
                    'drug' => $drugName,
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function fetchGoogleImage(string $drugName, array &$debug): ?string
    {
        $apiKey = trim((string) (
            $_ENV['GOOGLE_API_KEY']
            ?? $_SERVER['GOOGLE_API_KEY']
            ?? ''
        ));
        $cseId = trim((string) (
            $_ENV['GOOGLE_CSE_ID']
            ?? $_SERVER['GOOGLE_CSE_ID']
            ?? ''
        ));
        if ($apiKey === '' || $cseId === '') {
            $debug[] = 'Google: GOOGLE_API_KEY/GOOGLE_CSE_ID manquant';
            return null;
        }

        $query = trim($drugName) . ' boite medicament photo';
        $endpoint = 'https://www.googleapis.com/customsearch/v1';

        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'query' => [
                    'key' => $apiKey,
                    'cx' => $cseId,
                    'q' => $query,
                    'searchType' => 'image',
                    'num' => 1,
                    'safe' => 'active',
                ],
                'timeout' => 12,
            ]);

            if ($response->getStatusCode() >= 400) {
                $debug[] = 'Google HTTP ' . $response->getStatusCode();
                return null;
            }

            $data = $response->toArray(false);
            if (!is_array($data) || !isset($data['items'][0]) || !is_array($data['items'][0])) {
                $debug[] = 'Google: aucun resultat image';
                return null;
            }

            $item = $data['items'][0];
            $thumb = (string) ($item['image']['thumbnailLink'] ?? '');
            if ($thumb !== '' && str_starts_with($thumb, 'http')) {
                return $thumb;
            }

            $link = (string) ($item['link'] ?? '');
            if ($link !== '' && str_starts_with($link, 'http')) {
                return $link;
            }
        } catch (\Throwable $e) {
            $debug[] = 'Google exception: ' . $e->getMessage();
            $this->logger->warning('Google image search failed.', [
                'drug' => $drugName,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function fetchSerpApiImage(string $drugName, array &$debug): ?string
    {
        $apiKey = trim((string) (
            $_ENV['SERPAPI_API_KEY']
            ?? $_SERVER['SERPAPI_API_KEY']
            ?? ''
        ));
        if ($apiKey === '') {
            $debug[] = 'SerpAPI: SERPAPI_API_KEY manquant';
            return null;
        }

        $query = trim($drugName) . ' boite medicament photo';
        $endpoint = 'https://serpapi.com/search.json';

        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'query' => [
                    'engine' => 'google_images',
                    'q' => $query,
                    'api_key' => $apiKey,
                    'num' => 1,
                ],
                'timeout' => 14,
            ]);

            if ($response->getStatusCode() >= 400) {
                $debug[] = 'SerpAPI HTTP ' . $response->getStatusCode();
                return null;
            }

            $data = $response->toArray(false);
            if (!is_array($data) || !isset($data['images_results'][0]) || !is_array($data['images_results'][0])) {
                $debug[] = 'SerpAPI: aucun resultat image';
                return null;
            }

            $item = $data['images_results'][0];
            $thumb = (string) ($item['thumbnail'] ?? '');
            if ($thumb !== '' && str_starts_with($thumb, 'http')) {
                return $thumb;
            }
            $orig = (string) ($item['original'] ?? '');
            if ($orig !== '' && str_starts_with($orig, 'http')) {
                return $orig;
            }
        } catch (\Throwable $e) {
            $debug[] = 'SerpAPI exception: ' . $e->getMessage();
            $this->logger->warning('SerpAPI image search failed.', [
                'drug' => $drugName,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function fetchBingImage(string $drugName, array &$debug): ?string
    {
        $apiKey = trim((string) (
            $_ENV['BING_IMAGE_SEARCH_KEY']
            ?? $_SERVER['BING_IMAGE_SEARCH_KEY']
            ?? ''
        ));
        if ($apiKey === '') {
            $debug[] = 'Bing: BING_IMAGE_SEARCH_KEY manquant';
            return null;
        }

        $endpoint = 'https://api.bing.microsoft.com/v7.0/images/search';
        $query = trim($drugName) . ' boite medicament photo';

        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'query' => [
                    'q' => $query,
                    'count' => 1,
                    'safeSearch' => 'Moderate',
                ],
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => 14,
            ]);

            if ($response->getStatusCode() >= 400) {
                $debug[] = 'Bing HTTP ' . $response->getStatusCode();
                return null;
            }

            $data = $response->toArray(false);
            if (!is_array($data) || !isset($data['value'][0]) || !is_array($data['value'][0])) {
                $debug[] = 'Bing: aucun resultat image';
                return null;
            }

            $item = $data['value'][0];
            $thumb = (string) ($item['thumbnailUrl'] ?? '');
            if ($thumb !== '' && str_starts_with($thumb, 'http')) {
                return $thumb;
            }
            $content = (string) ($item['contentUrl'] ?? '');
            if ($content !== '' && str_starts_with($content, 'http')) {
                return $content;
            }
        } catch (\Throwable $e) {
            $debug[] = 'Bing exception: ' . $e->getMessage();
            $this->logger->warning('Bing image search failed.', [
                'drug' => $drugName,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function findSetIdByDrugName(string $drugName): ?string
    {
        $endpoint = rtrim($this->dailyMedBaseUrl, '/') . '/v1/drugname/' . rawurlencode($drugName) . '/spls.json';

        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            $data = $response->toArray(false);
            if (!is_array($data)) {
                return null;
            }

            if (isset($data['data'][0]['setid']) && is_string($data['data'][0]['setid'])) {
                return $data['data'][0]['setid'];
            }
            if (isset($data['spls'][0]['setid']) && is_string($data['spls'][0]['setid'])) {
                return $data['spls'][0]['setid'];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('DailyMed setid lookup failed.', [
                'drug' => $drugName,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function buildFallbackSvgDataUri(string $name): string
    {
        $label = mb_substr($name, 0, 24);
        $label = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="220" viewBox="0 0 320 220">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#0ea5a4"/>
      <stop offset="100%" stop-color="#2563eb"/>
    </linearGradient>
  </defs>
  <rect width="320" height="220" rx="20" fill="url(#g)"/>
  <ellipse cx="85" cy="78" rx="28" ry="28" fill="#ffffff" fill-opacity="0.16"/>
  <rect x="122" y="60" width="130" height="32" rx="16" fill="#ffffff" fill-opacity="0.22"/>
  <rect x="84" y="120" width="152" height="56" rx="28" fill="#ffffff" fill-opacity="0.9"/>
  <line x1="160" y1="120" x2="160" y2="176" stroke="#2563eb" stroke-width="3"/>
  <text x="160" y="206" text-anchor="middle" fill="#eaf4ff" font-family="Arial, sans-serif" font-size="17" font-weight="700">{$label}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function generateImageWithHuggingFace(string $drugName): ?string
    {
        $token = trim((string) (
            $_ENV['HF_TOKEN']
            ?? $_SERVER['HF_TOKEN']
            ?? ''
        ));
        if ($token === '') {
            return null;
        }

        $model = trim((string) (
            $_ENV['HF_IMAGE_MODEL']
            ?? $_SERVER['HF_IMAGE_MODEL']
            ?? 'stabilityai/stable-diffusion-xl-base-1.0'
        ));
        $endpoint = 'https://api-inference.huggingface.co/models/' . rawurlencode($model);

        $prompt = sprintf(
            "Clean studio photo of a medicine package labeled '%s', pharmaceutical product photography, white background, realistic lighting.",
            $drugName
        );

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'image/png',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'inputs' => $prompt,
                    'options' => ['wait_for_model' => true],
                ],
                'timeout' => 60,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 400) {
                return null;
            }

            $headers = $response->getHeaders(false);
            $contentType = isset($headers['content-type'][0]) ? (string) $headers['content-type'][0] : '';
            if (!str_starts_with($contentType, 'image/')) {
                return null;
            }

            $binary = $response->getContent(false);
            if ($binary === '') {
                return null;
            }

            return 'data:' . $contentType . ';base64,' . base64_encode($binary);
        } catch (\Throwable $e) {
            $this->logger->warning('HuggingFace image generation failed.', [
                'drug' => $drugName,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function generateImageWithPollinations(string $drugName): ?string
    {
        $prompt = sprintf(
            "medicine box photo labeled %s, pharmacy product, white background, realistic",
            $drugName
        );
        // Return a direct URL so the browser loads the generated image even
        // when server-side HTTP calls are blocked/limited.
        return 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt)
            . '?width=512&height=384&nologo=true&seed=' . random_int(1000, 999999);
    }
}
