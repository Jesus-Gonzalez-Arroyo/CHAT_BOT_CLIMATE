<?php

namespace App\Http\Controllers;

use App\Actions\SendMessageAction;
use App\Http\Requests\SendMessageRequest;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    public function store(
        SendMessageRequest $request,
        SendMessageAction $action
    ): JsonResponse {
        try {
            $messages = $action->execute(
                $request->input('conversation_id'),
                $request->input('content')
            );

            return response()->json($messages);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

# cGFuZ29saW4=
