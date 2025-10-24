<template>
  <div class="w-80 bg-white border-r flex flex-col h-full">
    <div class="p-4 border-b">
      <button
        @click="handleCreateConversation"
        class="w-full bg-blue-600 text-white rounded-lg px-4 py-2 hover:bg-blue-700 transition-colors"
      >
        Nueva Conversación
      </button>
    </div>
    
    <div class="flex-1 overflow-y-auto">
      <div 
        v-for="conversation in sortedConversations" 
        :key="conversation.id"
        @click="handleSelectConversation(conversation.id)"
        class="p-4 hover:bg-gray-50 cursor-pointer transition-colors border-b"
        :class="{'bg-gray-100': conversation.id === activeConversationId}"
      >
        <div class="flex justify-between items-start mb-1">
          <h3 class="font-medium text-gray-900 truncate">
            {{ conversation.title || 'Nueva Conversación' }}
          </h3>
          <button
            @click.stop="handleDelete(conversation.id)"
            class="text-gray-400 hover:text-red-500"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
        <p class="text-sm text-gray-500 truncate">
          {{ conversation.preview || 'No hay mensajes' }}
        </p>
        <p class="text-xs text-gray-400 mt-1">
          {{ formatDate(conversation.last_message_at) }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  conversations: {
    type: Array,
    required: true
  },
  activeConversationId: {
    type: Number,
    default: null
  }
});

const emit = defineEmits(['select-conversation', 'create-conversation', 'delete-conversation']);

const sortedConversations = computed(() => {
  return [...props.conversations].sort((a, b) => {
    return new Date(b.last_message_at || 0) - new Date(a.last_message_at || 0);
  });
});

const formatDate = (date) => {
  if (!date) return '';
  
  const d = new Date(date);
  const now = new Date();
  const diff = now - d;
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  
  if (days === 0) {
    return d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
  } else if (days === 1) {
    return 'Ayer';
  } else if (days < 7) {
    return d.toLocaleDateString('es-ES', { weekday: 'long' });
  } else {
    return d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  }
};

const handleCreateConversation = () => {
  emit('create-conversation');
};

const handleSelectConversation = (conversationId) => {
  emit('select-conversation', conversationId);

};

const handleDelete = (conversationId) => {
  if (confirm('¿Estás seguro de que deseas eliminar esta conversación?')) {
    emit('delete-conversation', conversationId);
  }
};
</script>