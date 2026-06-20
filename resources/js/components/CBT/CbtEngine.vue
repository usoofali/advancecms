<template>
  <div v-if="!isInitialized" class="flex justify-center items-center py-20">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600"></div>
  </div>
  
  <div v-else-if="isSubmitted" class="text-center py-12 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 max-w-2xl mx-auto mt-12 px-6">
    <div class="mx-auto h-20 w-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mb-6">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
    </div>
    <h2 class="text-3xl font-bold mb-3 text-zinc-900 dark:text-white">Assessment Submitted!</h2>
    <p class="text-zinc-500 dark:text-zinc-400 mb-8 text-lg">Your responses have been recorded successfully.</p>
    
    <div v-if="showResults" class="mb-10 p-6 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-800 rounded-2xl max-w-sm mx-auto shadow-sm">
      <div class="text-sm text-zinc-500 dark:text-zinc-400 font-medium mb-2 uppercase tracking-wide">Your Score</div>
      <div class="text-5xl font-black text-emerald-600 dark:text-emerald-400 flex items-baseline justify-center gap-1">
        {{ finalScore }} <span class="text-2xl text-zinc-400 dark:text-zinc-500 font-bold">/ {{ maxScore }}</span>
      </div>
    </div>
    
    <a :href="dashboardUrl" class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-lg font-medium rounded-xl shadow-sm text-white bg-emerald-600 hover:bg-emerald-700 transition-colors">
      Return to Dashboard
    </a>
  </div>

  <div v-else>
    <!-- Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-6 gap-4">
      <div>
        <h1 class="text-2xl font-bold">{{ testTitle }}</h1>
        <p class="text-zinc-500">{{ courseCode }} - {{ courseTitle }}</p>
      </div>
      
      <div class="flex items-center gap-3">
        <CountdownTimer 
          v-if="hasDuration"
          :remaining-seconds="remainingSeconds" 
          :is-offline="isOffline" 
        />
        
        <button 
          @click="confirmSubmit"
          class="px-5 py-2.5 text-sm font-medium rounded-full transition-colors flex items-center gap-2 bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100 shadow-sm"
        >
          Submit
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Offline Alert -->
    <div v-if="isOffline" class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-start gap-3">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mt-0.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3m8.293 8.293l1.414 1.414" />
      </svg>
      <div>
        <strong class="block font-bold">Network Connection Lost</strong>
        <span class="text-sm">Your timer has been paused. Your answers are safely saved locally. Please reconnect to continue.</span>
      </div>
    </div>

    <div v-if="!isOffline" class="max-w-4xl mx-auto relative mt-8">
      <!-- Progress Bar -->
      <div class="mb-6">
        <div class="flex justify-between items-center text-sm font-medium text-zinc-600 dark:text-zinc-400 mb-2">
          <span>Progress</span>
          <span>{{ answeredCount }} of {{ questions.length }} Answered</span>
        </div>
        <div class="w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-2.5 overflow-hidden">
          <div class="bg-emerald-500 h-2.5 rounded-full transition-all duration-300 ease-out" :style="{ width: progressPercentage + '%' }"></div>
        </div>
      </div>

      <!-- Main Content -->
      <QuestionCard 
        v-if="currentQuestion"
        :question="currentQuestion"
        :question-index="activeQuestionIndex"
        :total-questions="questions.length"
        :selected-option="answers[currentQuestion.id]"
        :is-first="activeQuestionIndex === 0"
        :is-last="activeQuestionIndex === questions.length - 1"
        @select-option="selectOption"
        @prev="prevQuestion"
        @next="nextQuestion"
        @submit="confirmSubmit"
      />
    </div>

    <!-- Submit Confirmation Modal -->
    <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/50 backdrop-blur-sm">
      <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform transition-all border border-zinc-200 dark:border-zinc-800">
        <div class="p-6 text-center">
          <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 dark:bg-emerald-900/40 mb-6">
            <svg class="h-8 w-8 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-zinc-900 dark:text-white mb-2">Submit Assessment?</h3>
          <p class="text-zinc-500 dark:text-zinc-400 mb-6">
            You have answered <strong class="text-zinc-800 dark:text-zinc-200">{{ answeredCount }}</strong> out of <strong class="text-zinc-800 dark:text-zinc-200">{{ questions.length }}</strong> questions. Once submitted, you cannot change your answers.
          </p>
          <div class="flex gap-3 w-full">
            <button 
              @click="showSubmitModal = false" 
              class="flex-1 px-4 py-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 font-medium hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
            >
              Cancel
            </button>
            <button 
              @click="executeSubmit" 
              class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-600 text-white font-medium hover:bg-emerald-700 transition-colors"
            >
              Confirm Submit
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Time Up Modal -->
    <div v-if="showTimeUpModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/50 backdrop-blur-sm">
      <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform transition-all border border-zinc-200 dark:border-zinc-800">
        <div class="p-6 text-center">
          <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-100 dark:bg-amber-900/40 mb-6">
            <svg class="h-8 w-8 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-zinc-900 dark:text-white mb-2">Time is Up!</h3>
          <p class="text-zinc-500 dark:text-zinc-400 mb-6">
            Your assessment time has expired. Your answers are being automatically submitted.
          </p>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import QuestionCard from './QuestionCard.vue';
import CountdownTimer from './CountdownTimer.vue';

const props = defineProps({
  payloadStr: {
    type: String,
    required: true
  }
});

const isInitialized = ref(false);
const isSubmitted = ref(false);
const isOffline = ref(!navigator.onLine);
const isSubmitting = ref(false);
const showSubmitModal = ref(false);
const showTimeUpModal = ref(false);

const testTitle = ref('');
const courseCode = ref('');
const courseTitle = ref('');
const dashboardUrl = ref('');
const submitUrl = ref('');
const extendUrl = ref('');

const questions = ref([]);
const answers = ref({});
const activeQuestionIndex = ref(0);

const showResults = ref(false);
const finalScore = ref(0);
const maxScore = ref(0);

const hasDuration = ref(false);
const remainingSeconds = ref(0);
let timerInterval = null;
let offlineSince = null;

const currentQuestion = computed(() => questions.value[activeQuestionIndex.value]);

const answeredCount = computed(() => {
  return Object.values(answers.value).filter(val => val !== null && val !== '').length;
});

const progressPercentage = computed(() => {
  if (questions.value.length === 0) return 0;
  return Math.round((answeredCount.value / questions.value.length) * 100);
});

onMounted(() => {
  try {
    // Parse the payload injected from Blade
    const payload = JSON.parse(props.payloadStr);
    
    testTitle.value = payload.testTitle;
    courseCode.value = payload.courseCode;
    courseTitle.value = payload.courseTitle;
    dashboardUrl.value = payload.dashboardUrl;
    submitUrl.value = payload.submitUrl;
    extendUrl.value = payload.extendUrl;
    questions.value = payload.questions;
    
    showResults.value = payload.showResults || false;
    maxScore.value = payload.questions.reduce((sum, q) => sum + (Number(q.marks) || 0), 0);
    
    hasDuration.value = payload.remainingSeconds !== null;
    remainingSeconds.value = payload.remainingSeconds || 0;

    // Initialize answers object and restore from localStorage if available
    const cacheKey = `cbt_attempt_${payload.attemptId}`;
    const cached = localStorage.getItem(cacheKey);
    
    if (cached) {
      answers.value = JSON.parse(cached);
    } else {
      questions.value.forEach(q => {
        answers.value[q.id] = null;
      });
    }

    // Setup network listeners
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Start timer or auto-submit if time is already up
    if (hasDuration.value) {
      if (remainingSeconds.value > 0) {
        startTimer();
      } else {
        showTimeUpModal.value = true;
        setTimeout(() => {
          submitTest();
        }, 2000);
      }
    }

    isInitialized.value = true;
  } catch (error) {
    console.error("Failed to parse CBT payload", error);
    alert("Failed to load test data. Please refresh the page.");
  }
});

onUnmounted(() => {
  window.removeEventListener('online', handleOnline);
  window.removeEventListener('offline', handleOffline);
  stopTimer();
});

async function handleOnline() {
  isOffline.value = false;
  
  if (hasDuration.value && offlineSince) {
    const offlineSeconds = Math.floor((Date.now() - offlineSince) / 1000);
    offlineSince = null;
    
    if (offlineSeconds > 0 && extendUrl.value) {
      try {
        const response = await axios.post(extendUrl.value, {
          offline_seconds: offlineSeconds
        });
        if (response.data && typeof response.data.remainingSeconds === 'number') {
          remainingSeconds.value = response.data.remainingSeconds;
        }
      } catch (e) {
        console.error("Failed to extend time", e);
      }
    }
  }

  if (hasDuration.value && remainingSeconds.value > 0) {
    startTimer();
  }
}

function handleOffline() {
  isOffline.value = true;
  offlineSince = Date.now();
  stopTimer();
}

function startTimer() {
  stopTimer();
  timerInterval = setInterval(() => {
    if (!isOffline.value && remainingSeconds.value > 0) {
      remainingSeconds.value--;
      if (remainingSeconds.value <= 0) {
        stopTimer();
        showTimeUpModal.value = true;
        setTimeout(() => {
          submitTest();
        }, 2000);
      }
    }
  }, 1000);
}

function stopTimer() {
  if (timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
  }
}

function selectOption(optionId) {
  answers.value[currentQuestion.value.id] = optionId;
  saveToLocal();
}

function saveToLocal() {
  try {
    const payload = JSON.parse(props.payloadStr);
    const cacheKey = `cbt_attempt_${payload.attemptId}`;
    localStorage.setItem(cacheKey, JSON.stringify(answers.value));
  } catch(e) {}
}

function prevQuestion() {
  if (activeQuestionIndex.value > 0) {
    activeQuestionIndex.value--;
  }
}

function nextQuestion() {
  if (activeQuestionIndex.value < questions.value.length - 1) {
    activeQuestionIndex.value++;
  }
}

function goToQuestion(index) {
  activeQuestionIndex.value = index;
}

function confirmSubmit() {
  if (isOffline.value) {
    alert("You are currently offline. Please reconnect to submit your test.");
    return;
  }
  
  showSubmitModal.value = true;
}

function executeSubmit() {
  showSubmitModal.value = false;
  submitTest();
}

async function submitTest() {
  if (isSubmitting.value) return;
  
  if (isOffline.value) {
    alert("You are currently offline. Please reconnect to submit your test.");
    return;
  }

  isSubmitting.value = true;
  stopTimer();

  try {
    // Send standard Axios POST
    // We expect standard Laravel CSRF to be handled by bootstrap.js / axios defaults
    const response = await axios.post(submitUrl.value, {
      answers: answers.value
    });
    
    if (response.data && response.data.score !== undefined) {
      finalScore.value = response.data.score;
    }
    
    // Clear cache
    const payload = JSON.parse(props.payloadStr);
    localStorage.removeItem(`cbt_attempt_${payload.attemptId}`);
    
    isSubmitted.value = true;
  } catch (error) {
    console.error(error);
    alert("There was an error submitting your test. Please try again.");
    isSubmitting.value = false;
    startTimer();
  }
}
</script>
