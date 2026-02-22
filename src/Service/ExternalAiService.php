<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExternalAiService
{
    public function __construct(
        private HttpClientInterface $client
    ) {
    }

    public function ask(string $message): string
    {
        $response = $this->client->request(
            'POST',
            'https://api.openai.com/v1/chat/completions',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $_ENV['OPENAI_KEY'],
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'model' => 'gpt-4.1-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a medical assistant.'],
                        ['role' => 'user', 'content' => $message]
                    ]
                ]
            ]
        );

        $data = $response->toArray();

        return $data['choices'][0]['message']['content'];
    }
}