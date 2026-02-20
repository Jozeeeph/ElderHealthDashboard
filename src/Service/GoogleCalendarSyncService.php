<?php

namespace App\Service;

use App\Entity\RendezVous;
use App\Repository\RendezVousRepository;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleCalendarSyncService
{
    private ?string $lastError = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly bool $enabled,
        private readonly string $calendarId,
        private readonly string $serviceAccountEmail,
        private readonly string $serviceAccountPrivateKey,
        private readonly string $timezone = 'UTC'
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled
            && $this->calendarId !== ''
            && $this->serviceAccountEmail !== ''
            && $this->serviceAccountPrivateKey !== '';
    }

    public function syncPlannedRendezVous(RendezVous $rdv): bool
    {
        $this->lastError = null;

        if (!$this->isEnabled()) {
            $this->lastError = 'Synchronisation Google desactivee ou credentials incomplets.';
            return false;
        }

        $eventWindow = $this->buildEventWindow($rdv);
        if ($eventWindow === null) {
            $this->lastError = 'Date/heure du rendez-vous invalide.';
            return false;
        }

        $token = $this->fetchAccessToken();
        if ($token === null) {
            if ($this->lastError === null) {
                $this->lastError = 'Impossible d obtenir un token OAuth Google.';
            }
            return false;
        }

        $rdvId = $rdv->getId();
        if (!is_int($rdvId) || $rdvId <= 0) {
            $this->lastError = 'ID rendez-vous manquant.';
            return false;
        }

        $payload = $this->buildEventPayload($rdv, $eventWindow['start'], $eventWindow['end']);
        $existingEventId = $this->findEventIdByRendezVousId($token, $rdvId);

        try {
            if ($existingEventId !== null) {
                $this->httpClient->request(
                    'PATCH',
                    $this->buildEventUrl($existingEventId),
                    [
                        'headers' => ['Authorization' => 'Bearer ' . $token],
                        'json' => $payload,
                    ]
                )->toArray(false);

                return true;
            }

            $this->httpClient->request(
                'POST',
                $this->buildEventsCollectionUrl(),
                [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                    'json' => $payload,
                ]
            )->toArray(false);

            return true;
        } catch (TransportExceptionInterface|\Throwable $e) {
            $this->lastError = 'Echec Google Calendar: ' . $e->getMessage();
            return false;
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}|null
     */
    private function buildEventWindow(RendezVous $rdv): ?array
    {
        if ($rdv->getDate() === null || $rdv->getHeure() === null) {
            return null;
        }

        $start = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $rdv->getDate()->format('Y-m-d') . ' ' . $rdv->getHeure()->format('H:i:s')
        );
        if (!$start instanceof \DateTimeImmutable) {
            return null;
        }

        $duration = RendezVousRepository::durationToMinutes($rdv->getTypeRendezVous()?->getDuree(), 45);
        $end = $start->modify('+' . $duration . ' minutes');

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEventPayload(RendezVous $rdv, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $patient = trim((string) ($rdv->getPatient()?->getPrenom() . ' ' . $rdv->getPatient()?->getNom()));
        $personnel = trim((string) ($rdv->getPersonnelMedical()?->getPrenom() . ' ' . $rdv->getPersonnelMedical()?->getNom()));
        $careType = (string) ($rdv->getTypeRendezVous()?->getType() ?? 'Rendez-vous');
        $phone = (string) ($rdv->getPatient()?->getNumeroTelephone() ?? '-');
        $status = (string) ($rdv->getEtat() ?? 'PLANIFIE');

        $description = implode("\n", [
            'Source: ElderHealthDashboard',
            'Patient: ' . ($patient !== '' ? $patient : 'Patient'),
            'Telephone: ' . $phone,
            'Type de soin: ' . $careType,
            'Personnel medical: ' . ($personnel !== '' ? $personnel : '-'),
            'Statut: ' . $status,
            'Lieu: ' . (string) ($rdv->getLieu() ?? '-'),
        ]);

        return [
            'summary' => ($patient !== '' ? $patient : 'Patient') . ' - ' . $careType,
            'description' => $description,
            'location' => (string) ($rdv->getLieu() ?? ''),
            'start' => [
                'dateTime' => $start->format(\DateTimeInterface::ATOM),
                'timeZone' => $this->timezone,
            ],
            'end' => [
                'dateTime' => $end->format(\DateTimeInterface::ATOM),
                'timeZone' => $this->timezone,
            ],
            'extendedProperties' => [
                'private' => [
                    'rdv_id' => (string) $rdv->getId(),
                    'source' => 'elder_health_dashboard',
                ],
            ],
        ];
    }

    private function fetchAccessToken(): ?string
    {
        $issuedAt = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $this->serviceAccountEmail,
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];

        $jwt = $this->buildSignedJwt($header, $claims);
        if ($jwt === null) {
            $this->lastError = 'Impossible de signer le JWT avec la cle privee.';
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            ]);
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable $e) {
            $this->lastError = 'Token OAuth Google refuse: ' . $e->getMessage();
            return null;
        }

        $token = $payload['access_token'] ?? null;
        return is_string($token) && $token !== '' ? $token : null;
    }

    private function buildSignedJwt(array $header, array $claims): ?string
    {
        $encodedHeader = $this->base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedClaims = $this->base64UrlEncode((string) json_encode($claims, JSON_UNESCAPED_SLASHES));
        $unsignedToken = $encodedHeader . '.' . $encodedClaims;

        $privateKey = str_replace('\n', "\n", $this->serviceAccountPrivateKey);
        $signature = '';
        $ok = openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if ($ok !== true) {
            return null;
        }

        return $unsignedToken . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function findEventIdByRendezVousId(string $token, int $rdvId): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $this->buildEventsCollectionUrl(), [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'query' => [
                    'privateExtendedProperty' => 'rdv_id=' . $rdvId,
                    'maxResults' => 1,
                    'singleEvents' => 'true',
                ],
            ]);
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable) {
            return null;
        }

        $items = $payload['items'] ?? null;
        if (!is_array($items) || $items === [] || !is_array($items[0] ?? null)) {
            return null;
        }

        $id = $items[0]['id'] ?? null;
        return is_string($id) && $id !== '' ? $id : null;
    }

    private function buildEventsCollectionUrl(): string
    {
        return sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events',
            rawurlencode($this->calendarId)
        );
    }

    private function buildEventUrl(string $eventId): string
    {
        return sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
            rawurlencode($this->calendarId),
            rawurlencode($eventId)
        );
    }
}
