<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WeatherService
{
    private const BASE_URL = 'https://api.open-meteo.com/v1';
    private const GEOCODING_URL = 'https://geocoding-api.open-meteo.com/v1';

    public function getWeatherByCity(string $city): array
    {
        try {
            $coordinates = $this->getCoordinates($city);
            
            if (!$coordinates) {
                throw new Exception("No se pudo encontrar la ubicación: {$city}");
            }

            $response = Http::get(self::BASE_URL . '/forecast', [
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
                'current_weather' => true,
                'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,weathercode',
                'timezone' => 'auto',
                'forecast_days' => 7,
            ]);

            if (!$response->successful()) {
                throw new Exception('Error al obtener datos del clima');
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Weather API Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getCoordinates(string $city): ?array
    {
        try {
            $response = Http::get(self::GEOCODING_URL . '/search', [
                'name' => $city,
                'count' => 1,
                'language' => 'es',
                'format' => 'json',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            
            if (empty($data['results'])) {
                return null;
            }

            return [
                'latitude' => $data['results'][0]['latitude'],
                'longitude' => $data['results'][0]['longitude'],
                'name' => $data['results'][0]['name'],
                'country' => $data['results'][0]['country'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('Geocoding API Error: ' . $e->getMessage());
            return null;
        }
    }

    public function interpretWeatherCode(int $code): string
    {
        return match(true) {
            $code === 0 => 'Despejado',
            $code >= 1 && $code <= 3 => 'Parcialmente nublado',
            $code >= 45 && $code <= 48 => 'Niebla',
            $code >= 51 && $code <= 55 => 'Llovizna',
            $code >= 56 && $code <= 57 => 'Llovizna helada',
            $code >= 61 && $code <= 65 => 'Lluvia',
            $code >= 66 && $code <= 67 => 'Lluvia helada',
            $code >= 71 && $code <= 75 => 'Nieve',
            $code === 77 => 'Granizo',
            $code >= 80 && $code <= 82 => 'Chubascos',
            $code >= 85 && $code <= 86 => 'Chubascos de nieve',
            $code >= 95 && $code <= 99 => 'Tormenta',
            default => 'Desconocido',
        };
    }
}

# cGFuZ29saW4=