<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Services\WeatherService;

class OpenAIService
{
    private const SYSTEM_PROMPT = <<<EOT
Eres un asistente virtual especializado en información meteorológica. Tu objetivo es ayudar a los usuarios a obtener información precisa y útil sobre el clima de manera amigable y concisa.

REGLAS:
1. Responde siempre en español
2. Mantén un tono amable y profesional
3. Usa emojis relevantes para hacer las respuestas más visuales
4. Cuando no tengas datos precisos, indícalo claramente
5. Si una pregunta no está relacionada con el clima, indica amablemente que solo puedes ayudar con temas meteorológicos

FORMATO DE RESPUESTA:
- Para pronósticos actuales:
  🌍 [Ciudad]
  📊 Ahora:
  - Temperatura: [X]°C
  - Condición: [descripción]
  - [otros datos relevantes]

- Para pronósticos futuros:
  🌍 [Ciudad] - [Periodo]
  📅 Previsión:
  - [Día]: [temperatura] | [condición]

EJEMPLOS DE USO:
P: ¿Necesitaré paraguas en Madrid mañana?
R: Déjame consultar el pronóstico para Madrid...
[consulta la API y responde según los datos]

P: ¿Hará calor este fin de semana en Barcelona?
R: Consultaré la previsión para Barcelona...
[consulta la API y responde según los datos]
EOT;

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
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT]
        ];

        foreach ($messages as $message) {
            // Si el mensaje es del asistente, lo incluimos tal cual
            if ($message['role'] === 'assistant') {
                $processedMessages[] = $message;
                continue;
            }

            // Si el mensaje es del usuario, intentamos enriquecerlo con datos del clima si es necesario
            if ($message['role'] === 'user') {
                $content = $message['content'];
                if ($cityName = $this->extractCityName($content)) {
                    try {
                        Log::info("Obteniendo datos del clima para: {$cityName}");
                        $weatherData = $this->weatherService->getWeatherByCity($cityName);
                        Log::info("Datos del clima obtenidos", ['data' => $weatherData]);
                        $content .= "\n\nDatos meteorológicos disponibles:\n" . json_encode($weatherData, JSON_PRETTY_PRINT);
                    } catch (Exception $e) {
                        Log::warning("No se pudieron obtener datos del clima para: {$cityName}", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
                $processedMessages[] = ['role' => 'user', 'content' => $content];
            }
        }

        return $processedMessages;
    }

    private function extractCityName(string $message): ?string
    {
        $patterns = [
            '/(?:en|de|para)\s+([A-ZÁ-Úa-zá-ú\s]+?)(?:\?|\.|$)/',
            '/clima (?:en|de)\s+([A-ZÁ-Úa-zá-ú\s]+?)(?:\?|\.|$)/',
            '/tiempo (?:en|de)\s+([A-ZÁ-Úa-zá-ú\s]+?)(?:\?|\.|$)/',
            '/(?:llover[áa]|llueve) (?:en|de)\s+([A-ZÁ-Úa-zá-ú\s]+?)(?:\?|\.|$)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }
}

# cGFuZ29saW4=
