<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Services\WeatherService;

class OpenAIService
{
    public function __construct(private WeatherService $weatherService) {}

    public function chat(array $messages): string
    {
        try {
            $processedMessages = $this->processMessages($messages);
            
            $apiKey = config('services.openai.api_key');
            if (empty($apiKey)) {
                throw new Exception('La API key de OpenAI no está configurada');
            }

            Log::info('Enviando petición a OpenAI', [
                'api_key_length' => strlen($apiKey),
                'messages_count' => count($processedMessages),
                'messages' => $processedMessages,
                'api_key_starts_with' => substr($apiKey, 0, 5) . '...',
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'User-Agent' => 'WeatherBot/1.0',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => $processedMessages,
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]);

            Log::info('Respuesta de OpenAI recibida', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'headers' => $response->headers()
            ]);

            if (!$response->successful()) {
                Log::error('Error de OpenAI', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'headers' => $response->headers()
                ]);
                throw new Exception('Error en la API de OpenAI: ' . $response->body());
            }

            $responseData = $response->json();
            return $responseData['choices'][0]['message']['content'];

        } catch (Exception $e) {
            Log::error('OpenAI API Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_messages' => $messages ?? null,
                'processed_messages' => $processedMessages ?? null,
                'api_key_exists' => !empty($apiKey),
            ]);
            throw new Exception('Error al procesar el mensaje: ' . $e->getMessage());
        }
    }

    private function processMessages(array $messages): array
    {
        $processedMessages = [
            ['role' => 'system', 'content' => config('prompts.weather_assistant')]
        ];

        $totalMessages = count($messages);
        if ($totalMessages === 0) {
            return $processedMessages;
        }

        $lastMessage = $messages[$totalMessages - 1];

        $currentCityQuery = null;
        if ($lastMessage['role'] === 'user') {
            $currentCityQuery = $this->extractCityName($lastMessage['content']);
        }
        
        if ($currentCityQuery) {
            
            $content = $lastMessage['content'];
            
            try {
                $weatherData = $this->weatherService->getWeatherByCity($currentCityQuery);

                $formattedData = $this->formatWeatherDataForAI($weatherData, $currentCityQuery);

                $content = "Usuario pregunta: {$lastMessage['content']}\n\n" . $formattedData;
                $content .= "\n\n INSTRUCCIÓN CRÍTICA: Responde sobre {$currentCityQuery} usando ÚNICAMENTE los datos meteorológicos proporcionados. Ignora cualquier otra ciudad que pueda aparecer en el contexto.";
            } catch (Exception $e) {
                Log::warning("No se pudieron obtener datos del clima para: {$currentCityQuery}", [
                    'error' => $e->getMessage()
                ]);
                $content .= "\n\n[No se pudieron obtener datos meteorológicos para {$currentCityQuery}]";
            }
            
            $processedMessages[] = ['role' => 'user', 'content' => $content];
        } else {
            
            $recentMessages = array_slice($messages, -3);
            
            foreach ($recentMessages as $message) {
                if ($message['role'] === 'assistant') {
                    $processedMessages[] = $message;
                    continue;
                }

                if ($message['role'] === 'user') {
                    $content = $message['content'];
                    $content = preg_replace('/═+.*?═+/s', '', $content);
                    $content = preg_replace('/\n\n.*?INSTRUCCIÓN.*$/s', '', $content);
                    $content = trim($content);
                    
                    $processedMessages[] = ['role' => 'user', 'content' => $content];
                }
            }
        }

        return $processedMessages;
    }

    private function extractCityName(string $message): ?string
    {
        $message = trim($message);
        
        $stopWords = ['clima', 'tiempo', 'temperatura', 'cual', 'cuál', 'sera', 'será', 'es', 'el', 'la', 'los', 'las', 
                      'hoy', 'mañana', 'manana', 'ahora', 'ayer', 'esta', 'este', 'para', 'dame', 'dime'];
        
        $patterns = [
            '/(?:clima|tiempo|temperatura|pronóstico|previsión)\s+(?:de|en)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+de\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)+)/ui',
            '/\b(?:de|en|para)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+de\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)+)/ui',
            '/(?:clima|tiempo|temperatura)\s+(?:de|en)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]{3,})\b/ui',
            '/\b(?:de|en)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]{3,})\b/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $cityName = trim($matches[1]);

                $cityName = preg_replace('/\s+(hoy|mañana|ahora|esta|ser[aá]|manana|ayer)$/ui', '', $cityName);
                $cityName = trim($cityName);

                if (strlen($cityName) < 3) {
                    continue;
                }
                
                $lowerCity = mb_strtolower($cityName, 'UTF-8');
                if (in_array($lowerCity, $stopWords)) {
                    continue;
                }
                
                return $cityName;
            }
        }

        return null;
    }

    private function formatWeatherDataForAI(array $weatherData, string $cityName): string
    {
        $current = $weatherData['current_weather'] ?? null;
        $daily = $weatherData['daily'] ?? null;
        
        if (!$current) {
            return "[DATOS METEOROLÓGICOS PARA: {$cityName}]\nNo hay datos disponibles.";
        }
        
        // Interpretar el código del clima
        $weatherCode = $current['weathercode'];
        $condition = $this->interpretWeatherCode($weatherCode);
        
        $formatted = "═══════════════════════════════════════\n";
        $formatted .= "📍 CIUDAD: {$cityName}\n";
        $formatted .= "🕐 CONSULTA: " . now()->format('Y-m-d H:i:s') . "\n";
        $formatted .= "═══════════════════════════════════════\n\n";
        
        $formatted .= "🌡️ CLIMA ACTUAL:\n";
        $formatted .= "  • Temperatura: {$current['temperature']}°C\n";
        $formatted .= "  • Condición: {$condition}\n";
        $formatted .= "  • Código clima: {$weatherCode}\n";
        $formatted .= "  • Velocidad viento: {$current['windspeed']} km/h\n";
        $formatted .= "  • Dirección viento: {$current['winddirection']}°\n";
        
        if ($daily) {
            $formatted .= "\n📅 PRONÓSTICO PRÓXIMOS DÍAS:\n";
            for ($i = 0; $i < min(3, count($daily['time'])); $i++) {
                $dayCondition = $this->interpretWeatherCode($daily['weathercode'][$i]);
                $formatted .= "  Día " . ($i + 1) . " ({$daily['time'][$i]}):\n";
                $formatted .= "    - Condición: {$dayCondition}\n";
                $formatted .= "    - Máxima: {$daily['temperature_2m_max'][$i]}°C\n";
                $formatted .= "    - Mínima: {$daily['temperature_2m_min'][$i]}°C\n";
                $formatted .= "    - Precipitación: {$daily['precipitation_sum'][$i]}mm\n";
            }
        }
        
        $formatted .= "\n═══════════════════════════════════════\n";
        
        return $formatted;
    }

    private function interpretWeatherCode(int $code): string
    {
        return match(true) {
            $code === 0 => 'Despejado ☀️',
            $code >= 1 && $code <= 3 => 'Parcialmente nublado ⛅',
            $code >= 45 && $code <= 48 => 'Niebla 🌫️',
            $code >= 51 && $code <= 55 => 'Llovizna 🌦️',
            $code >= 56 && $code <= 57 => 'Llovizna helada 🌨️',
            $code >= 61 && $code <= 65 => 'Lluvia 🌧️',
            $code >= 66 && $code <= 67 => 'Lluvia helada 🌨️',
            $code >= 71 && $code <= 75 => 'Nieve ❄️',
            $code === 77 => 'Granizo 🌨️',
            $code >= 80 && $code <= 82 => 'Chubascos 🌧️',
            $code >= 85 && $code <= 86 => 'Chubascos de nieve ❄️',
            $code >= 95 && $code <= 99 => 'Tormenta ⛈️',
            default => 'Desconocido',
        };
    }
}
