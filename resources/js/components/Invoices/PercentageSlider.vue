<template>
  <div class="space-y-2 p-3 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl border border-zinc-200/80 dark:border-zinc-800 transition-all">
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-1.5">
        <svg class="size-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
          {{ label || 'Allowable Payment Threshold' }}
        </span>
      </div>
      <span
        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold font-mono transition-colors"
        :class="badgeClass"
      >
        {{ currentPercent }}%
      </span>
    </div>

    <div class="flex items-center gap-3">
      <span class="text-[11px] font-mono text-zinc-400 shrink-0">0%</span>
      <div class="relative w-full flex items-center">
        <input
          type="range"
          min="0"
          max="100"
          step="1"
          v-model.number="currentPercent"
          @input="onSliderInput"
          @change="onSliderInput"
          class="w-full h-2 bg-zinc-200 dark:bg-zinc-700 rounded-lg appearance-none cursor-pointer accent-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
        />
      </div>
      <span class="text-[11px] font-mono text-zinc-400 shrink-0">100%</span>
    </div>

    <p v-if="description" class="text-[11px] text-zinc-500 dark:text-zinc-400 leading-tight">
      {{ description }}
      <span class="font-medium text-zinc-700 dark:text-zinc-300">
        (Required: at least {{ currentPercent }}% payment)
      </span>
    </p>

    <!-- Hidden input for form submission & Livewire synchronization -->
    <input
      type="hidden"
      :name="inputName"
      :value="currentPercent"
      ref="hiddenInputRef"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  initialValue: {
    type: Number,
    default: 100
  },
  label: {
    type: String,
    default: ''
  },
  description: {
    type: String,
    default: ''
  },
  inputName: {
    type: String,
    default: ''
  }
});

const currentPercent = ref(props.initialValue ?? 100);
const hiddenInputRef = ref(null);

watch(() => props.initialValue, (newVal) => {
  currentPercent.value = newVal ?? 100;
});

const badgeClass = computed(() => {
  if (currentPercent.value >= 100) {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
  } else if (currentPercent.value >= 50) {
    return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border border-blue-200 dark:border-blue-800';
  } else if (currentPercent.value > 0) {
    return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800';
  } else {
    return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700';
  }
});

function onSliderInput() {
  if (hiddenInputRef.value) {
    hiddenInputRef.value.value = currentPercent.value;
    hiddenInputRef.value.dispatchEvent(new Event('input', { bubbles: true }));
    hiddenInputRef.value.dispatchEvent(new Event('change', { bubbles: true }));
  }
}
</script>
