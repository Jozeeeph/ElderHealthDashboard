<?php

namespace App\Service;

use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StripeCheckoutService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $stripeSecretKey = '',
        private readonly string $stripeCurrency = 'eur',
        private readonly string $stripeApiBaseUrl = 'https://api.stripe.com'
    ) {
    }

    public function createCheckoutUrl(
        RendezVous $rdv,
        Utilisateur $patient,
        string $successUrl,
        string $cancelUrl
    ): string {
        $secret = trim($this->stripeSecretKey);
        if ($secret === '') {
            throw new \RuntimeException('Configuration Stripe absente: STRIPE_SECRET_KEY.');
        }

        $tarif = (float) ($rdv->getTypeRendezVous()?->getTarif() ?? 0);
        if ($tarif <= 0) {
            throw new \RuntimeException('Tarif du rendez-vous invalide.');
        }

        $unitAmount = (int) round($tarif * 100);
        $currency = strtolower(trim($this->stripeCurrency)) ?: 'eur';

        $name = sprintf(
            'Rendez-vous %s (%s)',
            $rdv->getTypeRendezVous()?->getType() ?? 'Medical',
            $rdv->getDate()?->format('d/m/Y') ?? '-'
        );

        try {
            $response = $this->httpClient->request('POST', rtrim($this->stripeApiBaseUrl, '/') . '/v1/checkout/sessions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                ],
                'body' => [
                    'mode' => 'payment',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'client_reference_id' => (string) $rdv->getId(),
                    'customer_email' => (string) $patient->getEmail(),
                    'metadata[rdv_id]' => (string) $rdv->getId(),
                    'metadata[patient_id]' => (string) $patient->getId(),
                    'line_items[0][price_data][currency]' => $currency,
                    'line_items[0][price_data][unit_amount]' => (string) $unitAmount,
                    'line_items[0][price_data][product_data][name]' => $name,
                    'line_items[0][quantity]' => '1',
                ],
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Echec de connexion a Stripe.', 0, $e);
        }

        $data = $response->toArray(false);
        $url = $data['url'] ?? null;
        if (!is_string($url) || $url === '') {
            $errorMessage = is_array($data['error'] ?? null) ? ($data['error']['message'] ?? null) : null;
            throw new \RuntimeException((string) ($errorMessage ?: 'Creation de session Stripe impossible.'));
        }

        return $url;
    }

    /**
     * @param array<int, array{name: string, unit_amount: int, quantity: int}> $items
     */
    public function createCartCheckoutUrl(
        array $items,
        ?Utilisateur $customer,
        string $successUrl,
        string $cancelUrl
    ): string {
        $secret = trim($this->stripeSecretKey);
        if ($secret === '') {
            throw new \RuntimeException('Configuration Stripe absente: STRIPE_SECRET_KEY.');
        }

        if ($items === []) {
            throw new \RuntimeException('Panier vide: impossible de creer une session Stripe.');
        }

        $currency = strtolower(trim($this->stripeCurrency)) ?: 'eur';
        $body = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => 'cart-' . uniqid('', true),
        ];

        if ($customer && $customer->getEmail()) {
            $body['customer_email'] = (string) $customer->getEmail();
            $body['metadata[user_id]'] = (string) $customer->getId();
        }

        foreach ($items as $index => $item) {
            if (($item['unit_amount'] ?? 0) <= 0 || ($item['quantity'] ?? 0) <= 0 || trim((string) ($item['name'] ?? '')) === '') {
                continue;
            }

            $i = (int) $index;
            $body[sprintf('line_items[%d][price_data][currency]', $i)] = $currency;
            $body[sprintf('line_items[%d][price_data][unit_amount]', $i)] = (string) $item['unit_amount'];
            $body[sprintf('line_items[%d][price_data][product_data][name]', $i)] = (string) $item['name'];
            $body[sprintf('line_items[%d][quantity]', $i)] = (string) $item['quantity'];
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->stripeApiBaseUrl, '/') . '/v1/checkout/sessions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                ],
                'body' => $body,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Echec de connexion a Stripe.', 0, $e);
        }

        $data = $response->toArray(false);
        $url = $data['url'] ?? null;
        if (!is_string($url) || $url === '') {
            $errorMessage = is_array($data['error'] ?? null) ? ($data['error']['message'] ?? null) : null;
            throw new \RuntimeException((string) ($errorMessage ?: 'Creation de session Stripe impossible.'));
        }

        return $url;
    }
}

