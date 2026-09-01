<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    payloadStr: {
        type: String,
        required: true,
    },
});

const type = ref('student');
const mode = ref('requests');
const items = ref([]);
const zoom = ref(1);

onMounted(() => {
    try {
        const parsed = JSON.parse(props.payloadStr);
        type.value = parsed.type || 'student';
        mode.value = parsed.mode || 'requests';
        items.value = parsed.items || [];
    } catch (e) {
        console.error('Failed to parse ID card print payload:', e);
    }
});

function printNow() {
    window.print();
}

function goBack() {
    window.history.back();
}

function zoomIn() {
    if (zoom.value < 1.5) zoom.value += 0.1;
}

function zoomOut() {
    if (zoom.value > 0.6) zoom.value -= 0.1;
}

function getOptimalFontSize(text, defaultPt, orientation = 'horizontal', maxCharsBase = null) {
    if (!text) return `${defaultPt}pt`;
    
    // Adjust base character count depending on orientation and if overridden
    let baseMaxChars = maxCharsBase;
    if (!baseMaxChars) {
        baseMaxChars = orientation === 'vertical' ? 18 : 24;
    }
    
    // Adjust based on font size (smaller default font can fit more characters)
    const maxChars = Math.round(baseMaxChars * (10 / defaultPt));
    
    if (text.length <= maxChars) return `${defaultPt}pt`;
    
    const calculatedPt = defaultPt * (maxChars / text.length);
    // Don't shrink below 4.5pt
    return Math.max(calculatedPt, 4.5) + 'pt';
}
</script>

<template>
    <div class="p-8 bg-zinc-100 min-h-screen print:p-0 print:bg-white font-sans text-zinc-900">
        <!-- Header Controls (No-Print) -->
        <div class="max-w-5xl mx-auto print:hidden mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-zinc-200">
            <div>
                <h1 class="text-2xl font-black text-zinc-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                    </svg>
                    <span>ID Card Print Studio</span>
                </h1>
                <p class="text-sm text-zinc-500 font-medium mt-1">
                    {{ items.length }} {{ items.length === 1 ? 'card' : 'cards' }} ready for high-precision printing (85.6mm × 53.98mm ISO/IEC 7810 ID-1 standard)
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Zoom Controls -->
                <div class="flex items-center bg-zinc-100 rounded-lg p-1 border border-zinc-200">
                    <button @click="zoomOut" class="px-2.5 py-1.5 text-xs font-bold text-zinc-700 hover:bg-white rounded transition" title="Zoom Out">
                        -
                    </button>
                    <span class="px-2 text-xs font-mono font-bold text-zinc-600">{{ Math.round(zoom * 100) }}%</span>
                    <button @click="zoomIn" class="px-2.5 py-1.5 text-xs font-bold text-zinc-700 hover:bg-white rounded transition" title="Zoom In">
                        +
                    </button>
                </div>

                <button @click="printNow" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-bold shadow-md hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Print Now</span>
                </button>
                <button @click="goBack" class="px-5 py-2.5 bg-white text-zinc-700 border border-zinc-300 rounded-xl font-bold hover:bg-zinc-50 active:scale-95 transition-all">
                    Go Back
                </button>
            </div>
        </div>

        <!-- Cards Container -->
        <div class="max-w-6xl mx-auto flex flex-col items-center gap-8 pb-16 print:gap-4 print:pb-0 transition-transform origin-top" :style="{ transform: `scale(${zoom})` }">
            <div v-if="items.length === 0" class="p-12 text-center bg-white rounded-2xl shadow-sm border border-zinc-200 w-full max-w-lg print:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-12 text-zinc-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                </svg>
                <h3 class="text-lg font-bold text-zinc-700">No Cards Selected</h3>
                <p class="text-sm text-zinc-500 mt-1">Please select ID card items from the management list to generate preview.</p>
            </div>

            <div v-for="(item, index) in items" :key="item.id" class="flex flex-col lg:flex-row items-center justify-center gap-6 print:gap-3 print:mb-6 break-inside-avoid id-card-pair">
                
                <!-- FRONT SIDE -->
                <div class="id-card-side relative overflow-hidden shadow-xl print:shadow-none print:border print:border-zinc-300 shrink-0 bg-white" 
                     :style="{ 
                        width: (item.template?.orientation === 'vertical' ? '53.98mm' : '85.6mm'), 
                        height: (item.template?.orientation === 'vertical' ? '85.6mm' : '53.98mm'), 
                        borderRadius: '3mm',
                        fontFamily: item.template?.font_family || 'Inter, sans-serif',
                        fontStyle: item.template?.font_style === 'italic' ? 'italic' : 'normal'
                     }">
                    
                    <!-- Background Watermark -->
                    <div v-if="item.template?.background_url" class="absolute inset-0 z-0 opacity-10" 
                         :style="{ backgroundImage: `url(${item.template.background_url})`, backgroundSize: 'cover', backgroundPosition: 'center' }">
                    </div>

                    <!-- Classic Layout -->
                    <template v-if="!item.template || item.template.layout === 'classic'">
                        <div class="w-full h-[15%] flex flex-col justify-center items-center px-4 shrink-0 shadow-sm relative z-10" 
                             :style="{ backgroundColor: item.template?.primary_color || '#2563eb', color: item.template?.text_color || '#ffffff' }">
                            <div class="w-full flex items-center justify-center gap-1.5">
                                <img v-if="item.institution.logo_url" :src="item.institution.logo_url" class="h-7 object-contain shrink-0">
                                <div :class="['font-bold uppercase tracking-wider text-center leading-[1.15]', item.template?.orientation === 'vertical' ? 'text-[5.5pt]' : 'text-[7pt] truncate']" 
                                     :style="item.template?.orientation === 'vertical' ? { display: '-webkit-box', WebkitLineClamp: '2', WebkitBoxOrient: 'vertical', overflow: 'hidden' } : {}">
                                    {{ item.template?.header_text || item.institution.name }}
                                </div>
                            </div>
                        </div>
                        <div class="p-3 flex gap-3 relative z-10 h-[85%]" 
                             :style="{ flexDirection: item.template?.orientation === 'vertical' ? 'column' : 'row', alignItems: item.template?.orientation === 'vertical' ? 'center' : 'flex-start' }">
                            
                            <div class="bg-white shrink-0 p-[1.5mm]" 
                                 :style="{ borderStyle: 'solid', borderWidth: (item.template?.photo_border_width ?? 2) + 'px', borderColor: item.template?.accent_color || '#f59e0b', width: item.template?.orientation === 'vertical' ? '24mm' : '20mm', height: item.template?.orientation === 'vertical' ? '28mm' : '24mm' }">
                                <img v-if="item.photo" :src="item.photo" class="w-full h-full object-cover">
                            </div>
                            
                            <div class="flex-1 flex flex-col w-full h-full pb-3" 
                                 :style="{ alignItems: item.template?.orientation === 'vertical' ? 'center' : 'flex-start', textAlign: item.template?.text_align || (item.template?.orientation === 'vertical' ? 'center' : 'left') }">
                                <div :class="['uppercase text-zinc-800 leading-tight', item.template?.font_weight || 'font-bold']"
                                     :style="{ fontSize: getOptimalFontSize(item.name, 10, item.template?.orientation), whiteSpace: 'nowrap' }">{{ item.name }}</div>
                                <div class="font-semibold mt-1 uppercase" 
                                     :style="{ color: item.template?.secondary_color || '#1e40af', fontSize: getOptimalFontSize(item.dept, 7, item.template?.orientation), whiteSpace: 'nowrap' }">{{ item.dept }}</div>
                                <div class="text-[7pt] mt-1 text-zinc-600 font-mono font-bold">ID: {{ item.idNumber }}</div>
                                
                                <div class="text-[6.5pt] mt-0.5 text-zinc-700 font-medium">Ph: {{ item.phone }}</div>
                                <div v-if="item.yearOfEntry" class="text-[6.5pt] mt-0.5 text-zinc-700 font-medium">Entry: {{ item.yearOfEntry }} <span v-if="item.duration" class="opacity-80">({{ item.duration }} Yrs)</span></div>
                                <div v-if="(item.template?.show_blood_group ?? true) && item.bloodGroup" class="text-[6.5pt] mt-0.5 text-zinc-700 font-medium">Blood Group: {{ item.bloodGroup }}</div>
                                
                                <div class="mt-auto w-full flex items-end justify-between" :style="{ flexDirection: (item.template?.text_align === 'center' || item.template?.orientation === 'vertical') ? 'column' : 'row', alignItems: (item.template?.text_align === 'center' || item.template?.orientation === 'vertical') ? 'center' : 'flex-end', justifyContent: 'space-between' }">
                                    <div v-if="item.signatureUrl" class="h-9">
                                        <img :src="item.signatureUrl" class="h-full object-contain mix-blend-multiply" />
                                    </div>
                                    <div v-else></div>
                                </div>
                            </div>
                            
                            <div v-if="item.template?.show_qr ?? true" class="absolute bottom-5 right-2 w-[12mm] h-[12mm] bg-white p-1 shadow-sm border border-zinc-200">
                                <img :src="item.qrUrl" class="w-full h-full object-contain">
                            </div>
                        </div>
                        <div class="absolute bottom-0 w-full p-1 text-center text-[5.5pt] z-10" 
                             :style="{ backgroundColor: item.template?.secondary_color || '#1e40af', color: item.template?.text_color || '#ffffff' }">
                            {{ item.template?.footer_text || (type === 'student' ? 'Student Identity Card' : 'Staff Identity Card') }}
                        </div>
                    </template>

                    <!-- Modern Sidebar Layout -->
                    <template v-if="item.template?.layout === 'modern_sidebar'">
                        <div class="flex h-full w-full relative z-10">
                            <div class="h-full w-[8mm] shrink-0 flex items-center justify-center shadow-md relative z-20" 
                                 :style="{ backgroundColor: item.template.primary_color, color: item.template.text_color }">
                                <div class="-rotate-90 text-[7pt] font-bold uppercase tracking-[0.2em] whitespace-nowrap">
                                    {{ item.template.header_text || (type === 'student' ? 'STUDENT ID' : 'STAFF ID') }}
                                </div>
                            </div>
                            <div class="flex-1 p-3 relative flex gap-3 h-full" 
                                 :style="{ flexDirection: item.template.orientation === 'vertical' ? 'column' : 'row', alignItems: item.template?.text_align === 'center' ? 'center' : 'flex-start' }">
                                
                                <div class="bg-white shrink-0 shadow-sm rounded-sm p-[1.5mm]" 
                                     :style="{ borderStyle: 'solid', borderWidth: (item.template?.photo_border_width ?? 2) + 'px', borderColor: item.template?.accent_color || '#e4e4e7', width: item.template.orientation === 'vertical' ? '24mm' : '20mm', height: item.template.orientation === 'vertical' ? '28mm' : '24mm' }">
                                    <img v-if="item.photo" :src="item.photo" class="w-full h-full object-cover rounded-[1px]">
                                </div>
                                
                                <div class="flex-1 flex flex-col h-full w-full relative" 
                                     :style="{ alignItems: item.template?.text_align === 'center' ? 'center' : 'flex-start', textAlign: item.template?.text_align || 'left' }">
                                    <div v-if="item.institution.logo_url" class="h-8 mb-1.5 shrink-0">
                                        <img :src="item.institution.logo_url" class="h-full object-contain">
                                    </div>
                                    <div :class="['uppercase text-zinc-800 tracking-tight leading-tight', item.template?.font_weight || 'font-extrabold']"
                                         :style="{ fontSize: getOptimalFontSize(item.name, 10, item.template.orientation, item.template.orientation === 'vertical' ? 15 : 21), whiteSpace: 'nowrap', maxWidth: item.template.show_qr && item.template.orientation === 'horizontal' ? 'calc(100% - 13mm)' : '100%' }">{{ item.name }}</div>
                                    <div class="font-bold mt-1 uppercase" 
                                         :style="{ color: item.template.primary_color, fontSize: getOptimalFontSize(item.dept, 7, item.template.orientation, item.template.orientation === 'vertical' ? 15 : 21), whiteSpace: 'nowrap', maxWidth: item.template.show_qr && item.template.orientation === 'horizontal' ? 'calc(100% - 13mm)' : '100%' }">{{ item.dept }}</div>
                                    <div class="text-[7pt] mt-1 text-zinc-600 font-mono font-bold">ID: {{ item.idNumber }}</div>
                                    
                                    <div class="text-[6.5pt] mt-0.5 text-zinc-600 font-medium">Ph: {{ item.phone }}</div>
                                    <div v-if="item.yearOfEntry" class="text-[6.5pt] mt-0.5 text-zinc-600 font-medium">Entry: {{ item.yearOfEntry }} <span v-if="item.duration" class="opacity-80">({{ item.duration }} Yrs)</span></div>
                                    <div v-if="(item.template?.show_blood_group ?? true) && item.bloodGroup" class="text-[6.5pt] mt-0.5 text-zinc-600 font-medium">Blood Group: {{ item.bloodGroup }}</div>
                                    
                                    <div class="mt-auto flex flex-col w-full" :style="{ alignItems: item.template?.text_align === 'center' ? 'center' : 'flex-start', paddingRight: item.template.show_qr ? '14mm' : '0' }">
                                        <div v-if="item.signatureUrl" class="h-8 mb-0.5">
                                            <img :src="item.signatureUrl" class="h-full object-contain mix-blend-multiply" />
                                        </div>
                                        <div class="text-[5.5pt] text-zinc-500 max-w-[100%] leading-tight pb-1" :style="{ textAlign: item.template?.text_align || 'left' }">
                                            {{ item.template.footer_text || item.institution.name }}
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-if="item.template.show_qr" class="absolute bottom-2 right-2 w-[12mm] h-[12mm] bg-white p-1 shadow-sm border border-zinc-200 rounded">
                                    <img :src="item.qrUrl" class="w-full h-full object-contain">
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Minimal Layout -->
                    <template v-if="item.template?.layout === 'minimal'">
                        <div class="w-full h-[1.5mm] relative z-20" :style="{ backgroundColor: item.template.primary_color }"></div>
                        <div class="flex h-full w-full p-4 relative z-10 gap-3" 
                             :style="{ flexDirection: item.template.orientation === 'vertical' ? 'column' : 'row', alignItems: 'center' }">
                            
                            <div class="bg-white shrink-0 rounded-lg shadow-sm p-[1.5mm]" 
                                 :style="{ borderStyle: 'solid', borderWidth: (item.template?.photo_border_width ?? 2) + 'px', borderColor: item.template?.accent_color || '#e4e4e7', width: item.template.orientation === 'vertical' ? '24mm' : '20mm', height: item.template.orientation === 'vertical' ? '28mm' : '24mm' }">
                                <img v-if="item.photo" :src="item.photo" class="w-full h-full object-cover rounded-md">
                            </div>
                            
                            <div class="flex-1 flex flex-col w-full" 
                                 :style="{ alignItems: item.template.orientation === 'vertical' ? 'center' : 'flex-start', textAlign: item.template?.text_align || (item.template.orientation === 'vertical' ? 'center' : 'left') }">
                                <div class="flex items-center gap-2 mb-1 justify-center sm:justify-start" :style="{ justifyContent: item.template.orientation === 'vertical' ? 'center' : 'flex-start' }">
                                    <img v-if="item.institution.logo_url" :src="item.institution.logo_url" class="h-7 object-contain">
                                    <div class="text-[6.5pt] font-semibold text-zinc-500 uppercase tracking-wider">
                                        {{ item.template.header_text || item.institution.name }}
                                    </div>
                                </div>
                                <div :class="['uppercase text-zinc-900 leading-tight', item.template?.font_weight || 'font-bold']"
                                     :style="{ fontSize: getOptimalFontSize(item.name, 10.5, item.template.orientation), whiteSpace: 'nowrap' }">{{ item.name }}</div>
                                <div class="mt-1 text-zinc-600 uppercase"
                                     :style="{ fontSize: getOptimalFontSize(item.dept, 7.5, item.template.orientation), whiteSpace: 'nowrap' }">{{ item.dept }}</div>
                                
                                <div class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[6.5pt] text-zinc-600 w-full" :style="{ justifyContent: item.template.orientation === 'vertical' ? 'center' : 'flex-start' }">
                                    <span class="font-bold">ID: {{ item.idNumber }}</span>
                                    <span v-if="item.template.orientation !== 'vertical'" class="opacity-50">|</span>
                                    <span>{{ item.phone }}</span>
                                </div>
                                <div v-if="item.yearOfEntry" class="text-[6.5pt] mt-0.5 text-zinc-600">
                                    Entry: {{ item.yearOfEntry }} <span v-if="item.duration" class="opacity-80">({{ item.duration }} Yrs)</span>
                                </div>
                                <div v-if="(item.template?.show_blood_group ?? true) && item.bloodGroup" class="text-[6.5pt] mt-0.5 text-zinc-600">
                                    Blood Group: {{ item.bloodGroup }}
                                </div>
                                
                                <div class="mt-2 w-full flex justify-between items-end" :style="{ flexDirection: item.template.orientation === 'vertical' ? 'column' : 'row', alignItems: item.template.orientation === 'vertical' ? 'center' : 'flex-end' }">
                                    <div v-if="item.signatureUrl" class="h-8">
                                        <img :src="item.signatureUrl" class="h-full object-contain mix-blend-multiply" />
                                    </div>
                                    <div v-else></div>
                                </div>
                            </div>
                            
                            <div v-if="item.template.show_qr" class="absolute bottom-3 right-3 w-[11mm] h-[11mm] bg-white shadow-sm border border-zinc-200 rounded-md p-1">
                                <img :src="item.qrUrl" class="w-full h-full object-contain">
                            </div>
                        </div>
                    </template>
                </div>

                <!-- BACK SIDE -->
                <div class="id-card-side relative overflow-hidden shadow-xl print:shadow-none print:border print:border-zinc-300 shrink-0" 
                     :style="{ 
                        width: (item.template?.orientation === 'vertical' ? '53.98mm' : '85.6mm'), 
                        height: (item.template?.orientation === 'vertical' ? '85.6mm' : '53.98mm'), 
                        borderRadius: '3mm',
                        backgroundColor: item.template?.back_background_color || '#f8fafc',
                        color: item.template?.back_text_color || '#3f3f46',
                        fontFamily: item.template?.font_family || 'Inter, sans-serif',
                        fontStyle: item.template?.font_style === 'italic' ? 'italic' : 'normal'
                     }">
                    <div class="p-3.5 flex flex-col h-full relative z-10">
                        <!-- 1. Logo Centered -->
                        <div v-if="item.institution.logo_url" class="w-full flex justify-center mb-2 shrink-0">
                            <div class="bg-white p-1 rounded-md border border-zinc-200 shadow-xs flex items-center justify-center">
                                <img :src="item.institution.logo_url" class="h-10 object-contain">
                            </div>
                        </div>

                        <!-- 2. Address & Contact Info -->
                        <div class="w-full mb-1.5 text-left shrink-0">
                            <p class="text-[6pt] font-bold leading-tight">{{ item.institution.address }}</p>
                            <div class="flex items-center gap-3 mt-1 text-[5pt] font-bold opacity-80">
                                <span class="flex items-center gap-0.5"><svg xmlns="http://www.w3.org/2000/svg" class="size-2 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg> {{ item.institution.phone }}</span>
                                <span class="flex items-center gap-0.5"><svg xmlns="http://www.w3.org/2000/svg" class="size-2 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg> {{ item.institution.email }}</span>
                            </div>
                        </div>

                        <!-- 3. Next of Kin -->
                        <div v-if="item.nextOfKinName || item.nextOfKinPhone" class="w-full text-left shrink-0 pb-1.5 pt-1 border-t border-dashed border-current border-opacity-20">
                            <div v-if="item.template?.orientation !== 'vertical'" class="flex items-baseline gap-2 flex-wrap">
                                <p class="text-[4.5pt] font-bold opacity-80 uppercase tracking-wider">Next of Kin:</p>
                                <p v-if="item.nextOfKinName" class="text-[5pt] font-bold leading-tight">{{ item.nextOfKinName }} <span v-if="item.nextOfKinRelationship" class="font-normal opacity-80">({{ item.nextOfKinRelationship }})</span></p>
                                <p v-if="item.nextOfKinPhone" class="text-[4.5pt] font-bold opacity-90"><span class="font-normal opacity-70 uppercase tracking-widest mr-0.5">TEL:</span>{{ item.nextOfKinPhone }}</p>
                            </div>
                            <template v-else>
                                <p class="text-[4.5pt] font-bold opacity-80 uppercase tracking-wider mb-0.5">Next of Kin:</p>
                                <p v-if="item.nextOfKinName" class="text-[5pt] font-bold leading-tight">{{ item.nextOfKinName }} <span v-if="item.nextOfKinRelationship" class="font-normal opacity-80">({{ item.nextOfKinRelationship }})</span></p>
                                <p v-if="item.nextOfKinPhone" class="text-[4.5pt] font-bold mt-0.5 opacity-90"><span class="font-normal opacity-70 uppercase tracking-widest mr-0.5">TEL:</span>{{ item.nextOfKinPhone }}</p>
                            </template>
                            <p v-if="item.nextOfKinAddress" class="text-[4.5pt] leading-tight mt-0.5 opacity-80" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ item.nextOfKinAddress }}</p>
                        </div>

                        <!-- 4. Disclaimer (Justified) -->
                        <div class="w-full border-t border-dashed border-current border-opacity-20 pt-1.5 flex-1 min-h-0">
                            <p class="text-[5.5pt] font-medium leading-[1.4] text-justify" style="text-align-last: center; display: -webkit-box; -webkit-line-clamp: 8; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ item.template?.disclaimer_text || ('This identity card remains the property of ' + item.institution.name + ' and relates only to the identity of the holder whose certified name and photograph are on the reverse side. If found, please return to the above address or the nearest Police Station.') }}
                            </p>
                        </div>

                        <!-- 4. Footer (Barcode & Signature) -->
                        <div class="flex items-end justify-between w-full mt-auto pt-1 gap-2 shrink-0">
                            <!-- Simulated Barcode -->
                            <div v-if="item.template?.show_barcode ?? true" class="h-3.5 w-24 bg-current bg-opacity-10 rounded-xs overflow-hidden flex items-end px-1 gap-px relative">
                                <div v-for="(w, idx) in [1,3,1,2,1,1,4,1,2,1,2,1,3,1,1,2,1,4,1,2,1,1,3]" :key="idx" class="bg-current h-full opacity-60" :style="{ width: w + 'px' }"></div>
                                <span class="absolute inset-0 flex items-center justify-center text-[5pt] tracking-widest font-mono text-current font-bold" style="text-shadow: 0 0 2px rgba(255,255,255,0.5)">{{ item.idNumber.replace(/[^a-zA-Z0-9]/g, '') }}</span>
                            </div>
                            <div v-else class="flex-1"></div>
                            
                            <div v-if="item.template?.show_signature_line ?? true" class="flex flex-col items-center shrink-0">
                                <div v-if="item.authorizedSignatureUrl" class="h-10 w-24 flex items-end justify-center border-b border-current opacity-80 pb-0.5">
                                    <img :src="item.authorizedSignatureUrl" class="h-full w-full object-contain mix-blend-multiply" style="transform: scale(1.2); transform-origin: bottom center;" />
                                </div>
                                <div v-else class="h-6 w-24 border-b border-current opacity-70"></div>
                                <span class="text-[4.5pt] font-bold uppercase mt-0.5 tracking-wider opacity-75">Authorized Signature</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=Roboto:ital,wght@0,400;0,700;1,400&display=swap');

.id-card-side {
    box-sizing: border-box;
    user-select: none;
    cursor: default;
    position: relative;
    overflow: hidden;
}

@media print {
    *, *::before, *::after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    body {
        background-color: white !important;
        background-image: none !important;
        color: black !important;
    }
    .id-card-pair {
        break-inside: avoid;
        page-break-inside: avoid;
        margin-bottom: 5mm !important;
    }
    .id-card-side {
        box-shadow: none !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
