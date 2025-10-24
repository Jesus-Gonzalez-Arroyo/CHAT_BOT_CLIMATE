<template>
  <div class="h-screen bg-gray-100 flex">
    <ConversationSidebar
      :conversations="conversations"
      :active-conversation-id="activeConversationId"
      @select-conversation="selectConversation"
      @create-conversation="createConversation"
      @delete-conversation="deleteConversation"
    />
        
    <div class="flex-1 flex flex-col">
      <ChatHeader 
        v-if="activeConversation"
        :title="activeConversation.title"
        :loading="loading"
      />

      <ChatMessages
        v-if="activeConversation"
        :messages="messages"
        :loading="loading"
      />

      <div v-else class="flex-1 flex items-center justify-center">
        <div class="text-center text-gray-500">
          <svg class="mx-auto h-24 w-24 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          <h3 class="text-xl font-semibold mb-2">¡Bienvenido a WeatherBot! 🌤️</h3>
          <p class="mb-4">Selecciona una conversación o crea una nueva para comenzar</p>
          <button
            @click="createConversation"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Iniciar Nueva Conversación
          </button>
        </div>
      </div>

      <ChatInput
        v-if="activeConversation"
        :loading="loading"
        :disabled="loading"
        @send-message="sendMessage"
      />
    </div>
    
    <ErrorNotification
      v-if="error"
      :message="error"
      @close="error = null"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import ConversationSidebar from './components/ConversationSidebar.vue';
import ChatHeader from './components/ChatHeader.vue';
import ChatMessages from './components/ChatMessages.vue';
import ChatInput from './components/ChatInput.vue';
import ErrorNotification from './components/ErrorNotification.vue';

const conversations = ref([]);
const messages = ref([]);
const activeConversationId = ref(null);
const loading = ref(false);
const error = ref(null);

const activeConversation = computed(() => {
  return conversations.value.find(c => c.id === activeConversationId.value);
});

onMounted(async () => {
  await loadConversations();
});

async function loadConversations() {
  try {
    const response = await fetch('/api/conversations');
    const data = await response.json();
    conversations.value = data.conversations;
  } catch (err) {
    error.value = 'Error al cargar las conversaciones';
  }
}

async function selectConversation(conversationId) {
  try {
    loading.value = true;
    activeConversationId.value = conversationId;
    
    const response = await fetch(`/api/conversations/${conversationId}`);
    const data = await response.json();
    messages.value = data.messages;
  } catch (err) {
    error.value = 'Error al cargar la conversación';
  } finally {
    loading.value = false;
  }
}

async function createConversation() {
  try {
    const response = await fetch('/api/conversations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    });
    
    const data = await response.json();
    conversations.value.unshift(data.conversation);
    activeConversationId.value = data.conversation.id;
    messages.value = [];
  } catch (err) {
    error.value = 'Error al crear la conversación';
  }
}

async function deleteConversation(conversationId) {
  try {
    await fetch(`/api/conversations/${conversationId}`, {
      method: 'DELETE',
    });
    
    conversations.value = conversations.value.filter(c => c.id !== conversationId);
    
    if (activeConversationId.value === conversationId) {
      activeConversationId.value = null;
      messages.value = [];
    }
  } catch (err) {
    error.value = 'Error al eliminar la conversación';
  }
}

async function sendMessage(content) {
  if (!content.trim() || loading.value) return;
  
  try {
    loading.value = true;
    
    const response = await fetch('/api/messages', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        conversation_id: activeConversationId.value,
        content: content.trim(),
      }),
    });
    
    if (!response.ok) {
      throw new Error('Error al enviar el mensaje');
    }
    
    const data = await response.json();
    messages.value.push(data.user_message);
    messages.value.push(data.assistant_message);
    
    const conversationIndex = conversations.value.findIndex(
      c => c.id === activeConversationId.value
    );
    if (conversationIndex !== -1) {
      const conversation = conversations.value[conversationIndex];
      conversation.last_message_at = new Date().toISOString();
      conversation.preview = data.assistant_message.content;
      conversations.value.splice(conversationIndex, 1);
      conversations.value.unshift(conversation);
    }
  } catch (err) {
    error.value = err.message || 'Error al enviar el mensaje';
  } finally {
    loading.value = false;
  }
}
</script>