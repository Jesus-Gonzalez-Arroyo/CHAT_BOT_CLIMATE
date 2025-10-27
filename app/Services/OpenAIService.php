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

REGLAS CRÍTICAS:
1. Responde siempre en español
2. Mantén un tono amable y profesional
3. Usa emojis relevantes para hacer las respuestas más visuales
4. **IMPORTANTE**: Cuando recibas "Datos meteorológicos ACTUALES para [Ciudad]", DEBES usar EXCLUSIVAMENTE esos datos para esa ciudad
5. **NUNCA uses información de una ciudad anterior cuando se te pregunta por una ciudad diferente**
6. **SIEMPRE verifica el nombre de la ciudad en los datos JSON antes de responder**
7. Si no tienes datos meteorológicos en el mensaje, indícalo claramente
8. Si una pregunta no está relacionada con el clima, indica amablemente que solo puedes ayudar con temas meteorológicos

FORMATO DE RESPUESTA:
- Para pronósticos actuales:
  🌍 [Ciudad EXACTA de los datos JSON]
  📊 Ahora:
  - Temperatura: [current_weather.temperature]°C
  - Condición: [interpretar weathercode]
  - Viento: [current_weather.windspeed] km/h

- Para pronósticos futuros:
  🌍 [Ciudad EXACTA] - Próximos días
  📅 Previsión:
  - Máxima: [daily.temperature_2m_max[0]]°C
  - Mínima: [daily.temperature_2m_min[0]]°C
  - Precipitación: [daily.precipitation_sum[0]]mm

PROCESO DE RESPUESTA:
1. Lee el mensaje del usuario
2. Busca "Datos meteorológicos ACTUALES para [Ciudad]" en el mensaje
3. Extrae la ciudad del mensaje de datos
4. Parsea el JSON y extrae los valores exactos
5. Responde usando SOLO esos datos para ESA ciudad específica
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
            Log::info("Detectada consulta de ciudad: {$currentCityQuery} - Enviando SIN historial");
            
            $content = $lastMessage['content'];
            
            try {
                Log::info("Obteniendo datos del clima FRESCOS para: {$currentCityQuery}");
                $weatherData = $this->weatherService->getWeatherByCity($currentCityQuery);
                Log::info("Datos del clima obtenidos para {$currentCityQuery}", ['data' => $weatherData]);
                
                $formattedData = $this->formatWeatherDataForAI($weatherData, $currentCityQuery);

                $content = "Usuario pregunta: {$lastMessage['content']}\n\n" . $formattedData;
                $content .= "\n\n⚠️ INSTRUCCIÓN CRÍTICA: Responde sobre {$currentCityQuery} usando ÚNICAMENTE los datos meteorológicos proporcionados. Ignora cualquier otra ciudad que pueda aparecer en el contexto.";
            } catch (Exception $e) {
                Log::warning("No se pudieron obtener datos del clima para: {$currentCityQuery}", [
                    'error' => $e->getMessage()
                ]);
                $content .= "\n\n[No se pudieron obtener datos meteorológicos para {$currentCityQuery}]";
            }
            
            $processedMessages[] = ['role' => 'user', 'content' => $content];
        } else {
            Log::info("Sin consulta de ciudad detectada - Usando historial limitado");
            
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

        Log::info("Mensajes procesados para OpenAI", [
            'total_original' => $totalMessages,
            'total_enviados' => count($processedMessages),
            'tiene_ciudad' => $currentCityQuery !== null,
            'ciudad' => $currentCityQuery
        ]);

        return $processedMessages;
    }

    private function extractCityName(string $message): ?string
    {
        $message = trim($message);
        Log::info("Intentando extraer ciudad de: '{$message}'");
        
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
                
                Log::info("✓ Ciudad extraída exitosamente: '{$cityName}'");
                return $cityName;
            }
        }

        Log::warning("✗ No se pudo extraer ciudad del mensaje: '{$message}'");
        return null;
    }

    private function formatWeatherDataForAI(array $weatherData, string $cityName): string
    {
        $current = $weatherData['current_weather'] ?? null;
        $daily = $weatherData['daily'] ?? null;
        
        if (!$current) {
            return "[DATOS METEOROLÓGICOS PARA: {$cityName}]\nNo hay datos disponibles.";
        }
        
        $formatted = "═══════════════════════════════════════\n";
        $formatted .= "📍 CIUDAD: {$cityName}\n";
        $formatted .= "🕐 CONSULTA: " . now()->format('Y-m-d H:i:s') . "\n";
        $formatted .= "═══════════════════════════════════════\n\n";
        
        $formatted .= "🌡️ CLIMA ACTUAL:\n";
        $formatted .= "  • Temperatura: {$current['temperature']}°C\n";
        $formatted .= "  • Código clima: {$current['weathercode']}\n";
        $formatted .= "  • Velocidad viento: {$current['windspeed']} km/h\n";
        $formatted .= "  • Dirección viento: {$current['winddirection']}°\n";
        
        if ($daily) {
            $formatted .= "\n📅 PRONÓSTICO PRÓXIMOS DÍAS:\n";
            for ($i = 0; $i < min(3, count($daily['time'])); $i++) {
                $formatted .= "  Día " . ($i + 1) . " ({$daily['time'][$i]}):\n";
                $formatted .= "    - Máxima: {$daily['temperature_2m_max'][$i]}°C\n";
                $formatted .= "    - Mínima: {$daily['temperature_2m_min'][$i]}°C\n";
                $formatted .= "    - Precipitación: {$daily['precipitation_sum'][$i]}mm\n";
            }
        }
        
        $formatted .= "\n═══════════════════════════════════════\n";
        
        return $formatted;
    }
}
