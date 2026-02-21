<?php
// src/Service/WeatherService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class WeatherService
{
    private $httpClient;
    private $cache;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
        $this->cache = new FilesystemAdapter();
    }

    public function getWeatherForDate(string $city, \DateTimeInterface $date): ?array
    {
        // Ne pas chercher la météo pour les dates passées
        $now = new \DateTime();
        if ($date < $now) {
            return null;
        }

        // Cache key unique (ville + date)
        $cacheKey = md5($city . $date->format('Y-m-d'));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($city, $date) {
            $item->expiresAfter(3600); // Cache 1 heure
            
            try {
                // Appel à l'API OpenWeatherMap
                $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/forecast', [
                    'query' => [
                        'q' => $city,
                        'appid' => $this->apiKey,
                        'units' => 'metric', // Pour les degrés Celsius
                        'lang' => 'fr'       // Pour les descriptions en français
                    ]
                ]);

                $data = $response->toArray();
                
                // Trouver la prévision pour la date spécifique
                $forecast = $this->findForecastForDate($data['list'], $date);
                
                if ($forecast) {
                    return [
                        'temp' => round($forecast['main']['temp']),
                        'ressenti' => round($forecast['main']['feels_like']),
                        'description' => $forecast['weather'][0]['description'],
                        'icon' => $forecast['weather'][0]['icon'],
                        'humidity' => $forecast['main']['humidity'],
                        'wind' => round($forecast['wind']['speed'] * 3.6), // Conversion en km/h
                        'pressure' => $forecast['main']['pressure']
                    ];
                }
                
                return null;
            } catch (\Exception $e) {
                // Log l'erreur mais retourne null pour ne pas casser la page
                return null;
            }
        });
    }

    private function findForecastForDate(array $forecasts, \DateTimeInterface $date): ?array
    {
        $targetDate = $date->format('Y-m-d');
        
        foreach ($forecasts as $forecast) {
            if (strpos($forecast['dt_txt'], $targetDate) === 0) {
                return $forecast;
            }
        }
        
        return null;
    }
}