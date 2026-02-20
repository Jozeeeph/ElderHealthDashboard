<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleCalendarService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly bool $enabled,
        private readonly string $calendarId,
        private readonly string $apiKey,
        private readonly string $timezone = 'UTC'
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->calendarId !== '' && $this->apiKey !== '';
    }

    public function getCalendarWebUrl(): ?string
    {
        if ($this->calendarId === '') {
            return null;
        }

        return sprintf(
            'https://calendar.google.com/calendar/u/0/r?cid=%s',
            rawurlencode($this->calendarId)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchEvents(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events',
            rawurlencode($this->calendarId)
        );

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'key' => $this->apiKey,
                    'timeMin' => $start->format(\DateTimeInterface::ATOM),
                    'timeMax' => $end->format(\DateTimeInterface::ATOM),
                    'singleEvents' => 'true',
                    'orderBy' => 'startTime',
                    'maxResults' => 2500,
                    'timeZone' => $this->timezone,
                ],
            ]);
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable) {
            return [];
        }

        $items = $payload['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $events = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['status'] ?? null) === 'cancelled') {
                continue;
            }

            $startAt = $this->extractDateValue($item['start'] ?? null);
            $endAt = $this->extractDateValue($item['end'] ?? null);
            if ($startAt === null || $endAt === null) {
                continue;
            }

            $events[] = [
                'id' => (string) ($item['id'] ?? sha1((string) $startAt->getTimestamp() . (string) $endAt->getTimestamp())),
                'title' => (string) ($item['summary'] ?? 'Rendez-vous'),
                'start' => $startAt->format(\DateTimeInterface::ATOM),
                'end' => $endAt->format(\DateTimeInterface::ATOM),
                'status' => 'PLANIFIE',
                'backgroundColor' => '#dbeafe',
                'borderColor' => '#2563eb',
                'textColor' => '#1e3a8a',
                'extendedProps' => [
                    'patientName' => (string) ($item['summary'] ?? 'Patient'),
                    'time' => $startAt->format('H:i'),
                    'careType' => (string) ($item['description'] ?? 'Google Calendar'),
                    'duration' => (int) max(1, floor(($endAt->getTimestamp() - $startAt->getTimestamp()) / 60)),
                    'status' => (string) ($item['status'] ?? 'confirmed'),
                    'location' => (string) ($item['location'] ?? '-'),
                    'isPaid' => false,
                    'externalProvider' => 'google_calendar',
                ],
            ];
        }

        return $events;
    }

    /**
     * @param mixed $rawValue
     */
    private function extractDateValue(mixed $rawValue): ?\DateTimeImmutable
    {
        if (!is_array($rawValue)) {
            return null;
        }

        $dateTime = $rawValue['dateTime'] ?? null;
        if (is_string($dateTime) && $dateTime !== '') {
            try {
                return new \DateTimeImmutable($dateTime);
            } catch (\Throwable) {
                return null;
            }
        }

        $date = $rawValue['date'] ?? null;
        if (!is_string($date) || $date === '') {
            return null;
        }

        $allDay = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$allDay instanceof \DateTimeImmutable) {
            return null;
        }

        return $allDay->setTime(0, 0, 0);
    }
}
