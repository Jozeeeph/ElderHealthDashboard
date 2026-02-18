<?php

namespace App\Service;

use App\Entity\Equipement;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TwilioSmsService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $accountSid,
        private readonly ?string $authToken,
        private readonly ?string $fromNumber,
        private readonly ?string $toNumber,
    ) {
    }

    public function sendStockOutAlert(Equipement $equipement): void
    {
        if (!$this->isConfigured()) {
            $this->logger->warning('Twilio SMS is not configured. Stock alert was skipped.');
            return;
        }

        $equipmentName = $equipement->getNom() ?? sprintf('#%d', (int) $equipement->getId());
        $message = sprintf(
            'Alerte stock: "%s" est en rupture de stock (quantite: %d).',
            $equipmentName,
            (int) ($equipement->getQuantiteDisponible() ?? 0)
        );

        try {
            $response = $this->httpClient->request('POST', sprintf(
                'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
                $this->accountSid
            ), [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => [
                    'From' => $this->fromNumber,
                    'To' => $this->toNumber,
                    'Body' => $message,
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                $this->logger->error('Twilio SMS request failed.', [
                    'status' => $response->getStatusCode(),
                    'response' => $response->getContent(false),
                ]);
            }
        } catch (TransportExceptionInterface|\Throwable $e) {
            $this->logger->error('Twilio SMS transport failed.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isConfigured(): bool
    {
        return (bool) ($this->accountSid && $this->authToken && $this->fromNumber && $this->toNumber);
    }
}
