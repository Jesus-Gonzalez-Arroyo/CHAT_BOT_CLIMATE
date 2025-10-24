<?php

namespace App\Actions;

use App\Models\Conversation;
use App\Models\Message;
use App\Enums\MessageRole;
use App\Services\OpenAIService;
use Exception;
use Illuminate\Support\Facades\DB;

class SendMessageAction
{
    public function __construct(
        private OpenAIService $openAIService
    ) {}

    public function execute(int $conversationId, string $userMessage): array
    {
        try {
            return DB::transaction(function () use ($conversationId, $userMessage) {
                $conversation = Conversation::findOrFail($conversationId);
                
                $userMessageRecord = Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::USER->value,
                    'content' => $userMessage,
                ]);

                $previousMessages = $conversation->messages()
                    ->latest()
                    ->take(10)
                    ->get()
                    ->reverse()
                    ->map(fn($msg) => [
                        'role' => $msg->role,
                        'content' => $msg->content
                    ])
                    ->toArray();

                $assistantResponse = $this->openAIService->chat($previousMessages);

                $assistantMessageRecord = Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::ASSISTANT->value,
                    'content' => $assistantResponse,
                ]);

                $conversation->update([
                    'last_message_at' => now(),
                    'title' => $conversation->title ?? $this->generateTitle($userMessage),
                ]);

                return [
                    'user_message' => [
                        'id' => $userMessageRecord->id,
                        'role' => $userMessageRecord->role,
                        'content' => $userMessageRecord->content,
                        'created_at' => $userMessageRecord->created_at,
                    ],
                    'assistant_message' => [
                        'id' => $assistantMessageRecord->id,
                        'role' => $assistantMessageRecord->role,
                        'content' => $assistantMessageRecord->content,
                        'created_at' => $assistantMessageRecord->created_at,
                    ],
                ];
            });
        } catch (Exception $e) {
            throw new Exception('Error al procesar el mensaje: ' . $e->getMessage());
        }
    }

    private function generateTitle(string $message): string
    {
        $title = substr($message, 0, 50);
        return strlen($message) > 50 ? $title . '...' : $title;
    }
}

# cGFuZ29saW4=