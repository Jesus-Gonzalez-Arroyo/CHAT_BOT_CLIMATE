<template>
  <div class="bg-white border-b px-4 py-3 flex items-center justify-between">
    <h2 class="text-xl font-semibold text-gray-800">{{ title }}</h2>
    <div class="flex items-center space-x-2">
      <span 
        v-if="loading"
        class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-blue-500 border-t-transparent"
      ></span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
});
</script>