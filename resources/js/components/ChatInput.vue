<template>
  <div class="border-t bg-white p-4">
    <form @submit.prevent="handleSubmit" class="flex space-x-4">
      <input
        v-model="message"
        type="text"
        placeholder="Escribe tu mensaje aquí..."
        class="flex-1 rounded-lg border border-gray-300 p-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        :disabled="loading || disabled"
      >
      <button
        type="submit"
        class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
        :disabled="loading || disabled || !message.trim()"
      >
        <span v-if="!loading">Enviar</span>
        <svg
          v-else
          class="h-5 w-5 animate-spin"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['send-message']);
const message = ref('');

function handleSubmit() {
  if (!message.value.trim() || props.loading || props.disabled) return;
  
  emit('send-message', message.value);
  message.value = '';
}
</script>