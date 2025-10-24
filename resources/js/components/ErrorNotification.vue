<template>
  <div 
    class="fixed inset-x-0 bottom-0 mb-4 flex justify-center pointer-events-none"
    :class="{ 'animate-fade-out': fadeOut }"
  >
    <div 
      class="bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg pointer-events-auto"
      @click="handleClose"
    >
      {{ message }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  message: {
    type: String,
    required: true
  }
});

const emit = defineEmits(['close']);
const fadeOut = ref(false);
let timeout;

onMounted(() => {
  timeout = setTimeout(() => {
    fadeOut.value = true;
    setTimeout(() => {
      emit('close');
    }, 300);
  }, 5000);
});

onBeforeUnmount(() => {
  if (timeout) clearTimeout(timeout);
});

function handleClose() {
  emit('close');
}
</script>

<style scoped>
.animate-fade-out {
  animation: fadeOut 0.3s ease-out forwards;
}

@keyframes fadeOut {
  from {
    opacity: 1;
    transform: translateY(0);
  }
  to {
    opacity: 0;
    transform: translateY(10px);
  }
}
</style>