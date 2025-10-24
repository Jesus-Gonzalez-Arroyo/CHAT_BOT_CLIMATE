<template>
  <div class="flex-1 overflow-y-auto p-4 space-y-4">
    <div 
      v-for="message in messages" 
      :key="message.id"
      :class="[
        'flex',
        message.role === 'user' ? 'justify-end' : 'justify-start'
      ]"
    >
      <div 
        :class="[
          'max-w-[80%] rounded-lg p-3',
          message.role === 'user' 
            ? 'bg-blue-600 text-white rounded-br-none' 
            : 'bg-gray-100 text-gray-800 rounded-bl-none'
        ]"
      >
        <div class="prose prose-sm" v-html="formatMessage(message.content)"></div>
      </div>
    </div>

    <div v-if="loading" class="flex justify-start">
      <div class="bg-gray-100 rounded-lg p-4 max-w-[80%]">
        <div class="flex space-x-2">
          <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
          <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:0.2s]"></div>
          <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:0.4s]"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { marked } from 'marked';
import DOMPurify from 'dompurify';

defineProps({
  messages: {
    type: Array,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
});

function formatMessage(content) {
  return DOMPurify.sanitize(marked(content));
}
</script>