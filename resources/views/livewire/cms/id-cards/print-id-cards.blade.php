<div class="p-8 bg-zinc-100 min-h-screen print:p-0 print:bg-white">
    <div class="max-w-4xl mx-auto no-print mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-black text-zinc-900">{{ __('ID Card Print Preview') }}</h1>
            <p class="text-sm text-zinc-500">{{ count($items) }} {{ __('cards ready for printing') }}</p>
        </div>
        <div class="flex gap-4">
            <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white rounded-lg font-bold shadow-lg hover:bg-blue-700 transition-all flex items-center gap-2">
                <flux:icon.printer class="size-4" />
                {{ __('Print Now') }}
            </button>
            <button onclick="window.history.back()" class="px-6 py-2 bg-white text-zinc-700 border border-zinc-200 rounded-lg font-bold hover:bg-zinc-50 transition-all">
                {{ __('Go Back') }}
            </button>
        </div>
    </div>

    <div class="flex flex-wrap justify-center gap-8 print:gap-4 print:block">
        @foreach($items as $item)
            @php
                if ($mode === 'requests') {
                    $user = $item->user;
                    $profile = $type === 'student' ? $user->student : $user->staff;
                    $institution = $item->institution;
                } else {
                    $profile = $item;
                    $user = $item->user;
                    $institution = $item->institution;
                }
                
                $name = $user->name;
                $idNumber = $type === 'student' ? ($profile->matric_number ?? 'N/A') : ($profile->staff_number ?? 'N/A');
                $photo = $profile->photo_path ? asset('storage/'.$profile->photo_path) : null;
                $dept = $type === 'student' ? ($profile->program?->name ?? 'N/A') : ($profile->designation ?? 'N/A');
                $qrData = route('home', ['verify_id' => $idNumber]); // Placeholder for verification
            @endphp

            @if($type === 'student')
                <!-- Student Card Design -->
                <div class="id-card student-card relative overflow-hidden bg-white shadow-2xl print:shadow-none print:border print:border-zinc-200 mb-8 print:mb-4 mx-auto" style="width: 85.6mm; height: 53.98mm; border-radius: 3mm; font-family: 'Inter', sans-serif;">
                    <!-- Header -->
                    <div class="absolute top-0 w-full h-12 flex items-center px-4 gap-3 text-white" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
                        <div class="size-8 bg-white rounded-full p-1 shrink-0 flex items-center justify-center overflow-hidden">
                            @if($institution->logo_url)
                                <img src="{{ $institution->logo_url }}" class="w-full h-full object-contain">
                            @else
                                <flux:icon.building-library class="size-5 text-blue-600" />
                            @endif
                        </div>
                        <div class="leading-none">
                            <h2 class="text-[10px] font-black uppercase tracking-tighter truncate w-48">{{ $institution->name }}</h2>
                            <p class="text-[7px] font-medium opacity-80 italic">{{ __('Identity Card') }}</p>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="mt-12 p-3 flex gap-4">
                        <!-- Photo -->
                        <div class="size-24 bg-zinc-100 rounded-lg border-2 border-blue-100 shrink-0 overflow-hidden shadow-sm">
                            @if($photo)
                                <img src="{{ $photo }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-blue-50">
                                    <flux:icon.user class="size-10 text-blue-200" />
                                </div>
                            @endif
                        </div>

                        <!-- Info -->
                        <div class="flex-1 flex flex-col justify-between py-1">
                            <div>
                                <h3 class="text-[11px] font-black text-blue-900 leading-tight uppercase">{{ $name }}</h3>
                                <p class="text-[8px] font-bold text-zinc-500 mt-0.5">{{ $dept }}</p>
                            </div>
                            
                            <div class="space-y-1">
                                <div class="flex flex-col">
                                    <span class="text-[6px] text-zinc-400 font-bold uppercase">{{ __('Matric Number') }}</span>
                                    <span class="text-[10px] font-black text-zinc-800 tracking-wider">{{ $idNumber }}</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[6px] text-zinc-400 font-bold uppercase">{{ __('Validity') }}</span>
                                    <span class="text-[8px] font-bold text-zinc-700">2024 - 2028</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer / Barcode -->
                    <div class="absolute bottom-0 w-full h-6 bg-zinc-50 border-t border-zinc-100 flex items-center justify-between px-4">
                        <span class="text-[8px] font-black text-blue-700 tracking-[0.2em]">{{ __('STUDENT') }}</span>
                        <div class="flex items-center gap-2">
                             <img src="https://api.qrserver.com/v1/create-qr-code/?size=40x40&data={{ urlencode($qrData) }}" class="size-5">
                             <div class="h-4 w-20 flex gap-0.5 items-end">
                                @foreach([2,1,3,1,2,2,1,3,1,1,2,1,3] as $w)
                                    <div class="bg-black h-full" style="width:{{ $w * 0.5 }}px"></div>
                                @endforeach
                             </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Staff Card Design -->
                <div class="id-card staff-card relative overflow-hidden text-white shadow-2xl print:shadow-none mb-8 print:mb-4 mx-auto" style="width: 85.6mm; height: 53.98mm; border-radius: 3mm; font-family: 'Inter', sans-serif; background: #0f172a;">
                    <!-- Geometric Accents -->
                    <div class="absolute -top-10 -right-10 size-40 bg-zinc-800/20 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-10 -left-10 size-40 bg-blue-900/20 rounded-full blur-2xl"></div>
                    
                    <!-- Header -->
                    <div class="absolute top-0 w-full p-3 flex justify-between items-start border-b border-white/5 bg-white/5 backdrop-blur-sm">
                        <div class="flex items-center gap-2">
                            <div class="size-6 bg-white rounded-md p-0.5 shrink-0 flex items-center justify-center">
                                @if($institution->logo_url)
                                    <img src="{{ $institution->logo_url }}" class="w-full h-full object-contain">
                                @else
                                    <flux:icon.building-library class="size-4 text-blue-600" />
                                @endif
                            </div>
                            <h2 class="text-[8px] font-black uppercase tracking-tight truncate w-40">{{ $institution->name }}</h2>
                        </div>
                        <div class="bg-blue-600 px-2 py-0.5 rounded text-[6px] font-black tracking-widest uppercase">{{ __('STAFF') }}</div>
                    </div>

                    <!-- Body -->
                    <div class="mt-10 p-3 flex flex-col items-center">
                        <div class="flex gap-4 w-full">
                            <!-- Photo (Centered in body) -->
                            <div class="size-24 bg-white/10 rounded-xl border border-white/20 p-1 flex-shrink-0">
                                <div class="w-full h-full rounded-lg overflow-hidden relative">
                                    @if($photo)
                                        <img src="{{ $photo }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-blue-900/50">
                                            <flux:icon.user class="size-10 text-white/20" />
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Info -->
                            <div class="flex-1 flex flex-col justify-center gap-2">
                                <div>
                                    <h3 class="text-[12px] font-black text-white leading-tight uppercase tracking-tight">{{ $name }}</h3>
                                    <p class="text-[8px] font-bold text-blue-400 mt-0.5">{{ $dept }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2 mt-1">
                                    <div>
                                        <span class="text-[6px] text-zinc-500 font-bold uppercase tracking-widest">{{ __('Staff ID') }}</span>
                                        <p class="text-[10px] font-black text-white tracking-widest">{{ $idNumber }}</p>
                                    </div>
                                    <div class="flex justify-end pr-2">
                                        <div class="bg-white p-0.5 rounded-sm">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=40x40&data={{ urlencode($qrData) }}" class="size-6">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Accent -->
                    <div class="absolute bottom-0 w-full h-1.5 bg-gradient-to-r from-blue-600 via-blue-400 to-blue-600"></div>
                </div>
            @endif

            <!-- Batch Separator for Print -->
            <div class="hidden print:block h-px w-full my-4"></div>
        @endforeach
    </div>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        
        .id-card {
            box-sizing: border-box;
            user-select: none;
            cursor: default;
        }

        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .id-card { 
                break-inside: avoid;
                margin-bottom: 5mm !important;
                box-shadow: none !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</div>
