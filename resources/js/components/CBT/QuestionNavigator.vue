<template>
  <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm p-6 sticky top-6">
    <h3 class="text-lg font-bold mb-4 text-zinc-900 dark:text-white">Question Navigator</h3>
    
    <div class="grid grid-cols-5 sm:grid-cols-8 lg:grid-cols-5 gap-2">
      <button 
        v-for="(question, index) in questions" 
        :key="question.id"
        @click="$emit('goTo', index)"
        class="aspect-square flex items-center justify-center rounded-lg font-mono font-medium text-sm transition-all"
        :class="[
          activeIndex === index 
            ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-zinc-900 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' 
            : (answers[question.id] 
                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' 
                : 'bg-zinc-50 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700')
        ]"
      >
        {{ index + 1 }}
      </button>
    </div>

    <div class="mt-6 space-y-2 text-sm border-t border-zinc-100 dark:border-zinc-800 pt-4">
      <div class="flex items-center gap-2">
        <div class="w-3 h-3 rounded-full bg-emerald-100 border border-emerald-200 dark:bg-emerald-900/40 dark:border-emerald-800"></div>
        <span class="text-zinc-600 dark:text-zinc-400">Answered ({{ answeredCount }})</span>
      </div>
      <div class="flex items-center gap-2">
        <div class="w-3 h-3 rounded-full bg-zinc-50 border border-zinc-200 dark:bg-zinc-800 dark:border-zinc-700"></div>
        <span class="text-zinc-600 dark:text-zinc-400">Unanswered ({{ questions.length - answeredCount }})</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  questions: {
    type: Array,
    required: true
  },
  answers: {
    type: Object,
    required: true
  },
  activeIndex: {
    type: Number,
    required: true
  }
});

defineEmits(['goTo']);

const answeredCount = computed(() => {
  return Object.values(props.answers).filter(val => val !== null && val !== '').length;
});
</script>
