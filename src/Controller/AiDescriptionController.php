<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AiDescriptionController extends AbstractController
{
    #[Route('/admin/api/generate-description', name: 'admin_api_generate_description', methods: ['POST'])]
    #[Route('/proprietaire/api/generate-description', name: 'front_proprietaire_api_generate_description', methods: ['POST'])]
    public function generate(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->json(['error' => 'Le nom de l\'equipement est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $apiKey = $_ENV['GROQ_API_KEY'] ?? $_SERVER['GROQ_API_KEY'] ?? '';
        if ($apiKey === '') {
            return $this->json(['error' => 'Configuration AI manquante (GROQ_API_KEY).'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $model = $_ENV['GROQ_MODEL'] ?? $_SERVER['GROQ_MODEL'] ?? 'llama-3.3-70b-versatile';
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $prompt = sprintf(
            'Redige une description professionnelle (2-3 phrases, ton clair) pour un equipement medical nomme "%s". Mets en avant l\'utilite, le confort et la fiabilite.',
            $name
        );

        try {
            $response = $httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 180,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode >= 400 || isset($data['error'])) {
                $errorMessage = (string) ($data['error']['message'] ?? ('HTTP ' . $statusCode));
                $clientStatus = in_array($statusCode, [401, 429], true) ? $statusCode : Response::HTTP_BAD_GATEWAY;

                return $this->json(['error' => 'Groq API Error: ' . $errorMessage], $clientStatus);
            }

            $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            if ($text === '') {
                return $this->json(['error' => 'Aucune description generee.'], Response::HTTP_BAD_GATEWAY);
            }

            return $this->json(['description' => $text]);
        } catch (TransportException $e) {
            return $this->json(['error' => 'Groq API Error: ' . $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable) {
            return $this->json(['error' => 'Erreur interne lors de la generation.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
