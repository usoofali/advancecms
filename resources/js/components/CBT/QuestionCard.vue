<template>
  <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col min-h-[400px]">
    <div class="p-6 md:p-8 flex-1">
      <div class="mb-4">
        <h2 class="text-md md:text-xl font-medium md:font-semibold leading-relaxed text-zinc-900 dark:text-white">
          <span class="text-zinc-400 mr-0 text-base md:text-xl">{{ questionIndex + 1 }}.</span>
          {{ question.text }}
        </h2>
      </div>

      <div class="space-y-4 mt-4">
        <label 
          v-for="(option, index) in question.options" 
          :key="option.id"
          class="flex items-center p-3 border rounded-xl cursor-pointer transition-all duration-200"
          :class="[
            selectedOption === option.id 
              ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-500' 
              : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800'
          ]"
        >
          <input 
            type="radio" 
            :name="'question_' + question.id" 
            :value="option.id"
            :checked="selectedOption === option.id"
            @change="$emit('selectOption', option.id)"
            class="h-5 w-5 text-emerald-600 focus:ring-emerald-500 border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800 cursor-pointer"
          >
          <div class="ml-4 flex items-start gap-2">
            <span class="font-bold text-zinc-500 dark:text-zinc-400 mt-0.5 md:mt-0">{{ String.fromCharCode(65 + index) }}.</span>
            <span class="text-zinc-800 dark:text-zinc-200 text-base md:text-lg">{{ option.text }}</span>
          </div>
        </label>
      </div>
    </div>
    
    <!-- Navigation Footer -->
    <div class="px-6 py-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between bg-zinc-50 dark:bg-zinc-900/50 rounded-b-xl">
      <button 
        @click="$emit('prev')" 
        :disabled="isFirst"
        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed text-zinc-700 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-zinc-800"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Previous
      </button>
      
      <span class="text-sm font-medium text-zinc-500">
        {{ questionIndex + 1 }} of {{ totalQuestions }}
      </span>
      
      <button 
        @click="$emit('next')"
        v-if="!isLast"
        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2 bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100"
      >
        Next
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
      
      <button 
        @click="$emit('submit')"
        v-if="isLast"
        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
      >
        Submit
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  question: {
    type: Object,
    required: true
  },
  questionIndex: {
    type: Number,
    required: true
  },
  totalQuestions: {
    type: Number,
    required: true
  },
  selectedOption: {
    type: [Number, String, null],
    default: null
  },
  isFirst: {
    type: Boolean,
    default: false
  },
  isLast: {
    type: Boolean,
    default: false
  }
});

defineEmits(['prev', 'next', 'submit', 'selectOption']);
</script>
