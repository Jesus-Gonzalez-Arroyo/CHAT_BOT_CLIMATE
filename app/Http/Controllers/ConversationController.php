<?php

namespace App\Http\Controllers;

use App\Actions\CreateConversationAction;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    public function index(): JsonResponse
    {
        $conversations = Conversation::with(['latestMessage' => function ($query) {
            $query->limit(1);
        }])
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'last_message_at' => $conversation->last_message_at,
                    'preview' => $conversation->latestMessage->first()?->content,
                ];
            });

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    public function store(CreateConversationAction $action): JsonResponse
    {
        $conversation = $action->execute();

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'last_message_at' => $conversation->last_message_at,
            ],
        ], 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at,
                ];
            });

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'last_message_at' => $conversation->last_message_at,
            ],
            'messages' => $messages,
        ]);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $conversation->delete();

        return response()->json([
            'message' => 'Conversación eliminada exitosamente',
        ]);
    }
}

# cGFuZ29saW4=