<template>
  <div :class="[
    'px-5 py-2.5 rounded-full font-mono font-bold flex items-center gap-3 border shadow-sm transition-colors',
    statusClass
  ]">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span>{{ displayText }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  remainingSeconds: {
    type: Number,
    required: true
  },
  isOffline: {
    type: Boolean,
    default: false
  }
});

const formattedTime = computed(() => {
  const h = Math.floor(props.remainingSeconds / 3600);
  const m = Math.floor((props.remainingSeconds % 3600) / 60);
  const s = props.remainingSeconds % 60;

  if (h > 0) {
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
  }
  return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
});

const displayText = computed(() => {
  if (props.isOffline) return 'OFFLINE - PAUSED';
  return formattedTime.value;
});

const statusClass = computed(() => {
  if (props.isOffline) {
    return 'bg-red-50 text-red-600 border-red-200';
  }
  if (props.remainingSeconds <= 60) {
    return 'bg-red-50 text-red-600 border-red-200 animate-pulse';
  }
  if (props.remainingSeconds <= 300) {
    return 'bg-amber-50 text-amber-600 border-amber-200';
  }
  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
});
</script>
