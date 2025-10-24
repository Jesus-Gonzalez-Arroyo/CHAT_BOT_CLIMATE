<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Enums\MessageRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $conversation = Conversation::create([
            'title' => 'Clima en Madrid',
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::USER->value,
            'content' => '¿Cómo está el clima en Madrid?',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::ASSISTANT->value,
            'content' => "🌍 **Clima en Madrid**\n\n📊 **Ahora:**\n- Temperatura: 22°C\n- Condición: Despejado\n- Velocidad del viento: 10 km/h",
        ]);
    }
}