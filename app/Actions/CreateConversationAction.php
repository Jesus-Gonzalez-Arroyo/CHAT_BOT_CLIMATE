<?php

namespace App\Actions;

use App\Models\Conversation;

class CreateConversationAction
{
    public function execute(): Conversation
    {
        return Conversation::create([
            'title' => 'Nueva Conversación',
            'last_message_at' => now(),
        ]);
    }
}

# cGFuZ29saW4=
