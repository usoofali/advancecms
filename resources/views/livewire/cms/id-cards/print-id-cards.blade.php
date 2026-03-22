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

    <div class="space-y-12 shrink-0">
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
                $phone = $profile->phone ?? 'N/A';
                $email = ($type === 'staff') ? ($profile->email ?? $user->email) : ($user->email ?? 'N/A');
                $dept = $type === 'student' ? ($profile->program?->department?->name ?? 'N/A') : ($profile->designation ?? 'N/A');
                $secondaryInfo = $type === 'student' ? ($profile->program?->name ?? 'N/A') : ($profile->institution?->name ?? 'N/A');
                $qrData = route('home', ['verify_id' => $idNumber]);
            @endphp

            <div class="flex flex-col md:flex-row items-center justify-center gap-8 print:gap-4 print:mb-12 break-inside-avoid">
                <!-- FRONT SIDE (Personal Info) -->
                <div class="id-card-side relative overflow-hidden bg-white shadow-2xl print:shadow-none print:border print:border-zinc-300" style="width: 85.6mm; height: 53.98mm; border-radius: 4mm; font-family: 'Inter', sans-serif;">
                    <!-- Aesthetic Ribbon -->
                    <div class="absolute top-0 right-0 w-24 h-24 -mt-12 -mr-12 {{ $type === 'student' ? 'bg-blue-600' : 'bg-slate-900' }} rotate-45 opacity-10"></div>
                    
                    <div class="flex h-full p-4 gap-4">
                        <!-- Photo Column -->
                        <div class="flex flex-col items-center gap-2 shrink-0">
                            <div class="size-28 rounded-2xl overflow-hidden border-[3px] border-white shadow-xl bg-zinc-100 relative z-10 ring-1 ring-zinc-200">
                                @if($photo)
                                    <img src="{{ $photo }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-zinc-50">
                                        <flux:icon.user class="size-12 text-zinc-200" />
                                    </div>
                                @endif
                            </div>
                            <div class="{{ $type === 'student' ? 'bg-blue-600' : 'bg-slate-900' }} text-white px-3 py-0.5 rounded-full shadow-sm relative z-20 -mt-3">
                                <span class="text-[8pt] font-black uppercase tracking-[0.1em]">{{ $type }}</span>
                            </div>
                        </div>

                        <!-- Data Column -->
                        <div class="flex-1 flex flex-col justify-center gap-3">
                            <div class="border-b-2 border-zinc-100 pb-2">
                                <h3 class="text-[14pt] font-black text-zinc-900 leading-tight uppercase tracking-tight">{{ $name }}</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-2">
                                <div class="flex flex-col">
                                    <span class="text-[7pt] text-zinc-400 font-black uppercase tracking-widest">{{ $type === 'student' ? __('Matric Number') : __('Staff ID') }}</span>
                                    <span class="text-[12pt] font-black text-zinc-800 tracking-wider font-mono">{{ $idNumber }}</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[7pt] text-zinc-400 font-black uppercase tracking-widest">{{ __('Department / Unit') }}</span>
                                    <span class="text-[10pt] font-bold text-zinc-700 truncate w-48">{{ $dept }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Small Corner Institution Logo -->
                    <div class="absolute bottom-4 left-4 opacity-20 size-8">
                        @if($institution->logo_url)
                            <img src="{{ $institution->logo_url }}" class="w-full h-full object-contain grayscale">
                        @endif
                    </div>
                </div>

                <!-- BACK SIDE (Institution Info) -->
                <div class="id-card-side relative overflow-hidden bg-white shadow-2xl print:shadow-none print:border print:border-zinc-300" style="width: 85.6mm; height: 53.98mm; border-radius: 4mm; font-family: 'Inter', sans-serif;">
                     <!-- Header Accent -->
                     <div class="absolute top-0 w-full h-12 {{ $type === 'student' ? 'bg-blue-600' : 'bg-slate-900' }} flex items-center px-6 gap-3 shadow-md">
                         <div class="size-8 bg-white rounded-lg p-1 flex items-center justify-center shadow-sm">
                             @if($institution->logo_url)
                                 <img src="{{ $institution->logo_url }}" class="w-full h-full object-contain">
                             @else
                                 <flux:icon.building-library class="size-5 text-blue-600" />
                             @endif
                         </div>
                         <h2 class="text-[10pt] font-black uppercase tracking-tight text-white truncate drop-shadow-sm">{{ $institution->name }}</h2>
                     </div>

                     <div class="mt-12 p-5 flex flex-col h-full overflow-hidden">
                         <div class="flex justify-between items-start gap-4 mb-4">
                             <!-- Contact Info -->
                             <div class="space-y-3">
                                 <div>
                                     <h4 class="text-[8pt] font-black uppercase tracking-[0.1em] text-zinc-400 mb-1">{{ __('Emergency Contact') }}</h4>
                                     <div class="flex items-center gap-2 text-zinc-700">
                                         <flux:icon.phone class="size-3 opacity-50" />
                                         <span class="text-[10pt] font-bold">{{ $phone }}</span>
                                     </div>
                                 </div>
                                 <div class="flex items-center gap-2 text-zinc-700">
                                     <flux:icon.envelope class="size-3 opacity-50" />
                                     <span class="text-[9pt] font-bold truncate w-40">{{ $email }}</span>
                                 </div>
                             </div>

                             <!-- QR Code -->
                             <div class="bg-zinc-50 p-1.5 rounded-xl border border-zinc-100 shadow-sm">
                                 <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={{ urlencode($qrData) }}" class="size-12">
                             </div>
                         </div>

                         <!-- Disclaimer & Signature -->
                         <div class="flex-1 border-t border-zinc-100 pt-3 relative">
                             <p class="text-[7pt] leading-[1.3] text-zinc-500 font-medium italic pr-24">
                                 {{ __('This card is the property of :inst. If found, please return to any nearest police station or notify the institution official.', ['inst' => $institution->name]) }}
                             </p>
                             
                             <div class="absolute right-0 bottom-2 flex flex-col items-center">
                                 <div class="h-6 w-20 border-b border-zinc-400 relative">
                                     <span class="absolute top-0 w-full text-center text-[7pt] font-cursive italic text-zinc-300">Auth. Signature</span>
                                 </div>
                             </div>
                         </div>

                         <!-- Barcode -->
                         <div class="h-5 w-full bg-zinc-50 rounded border border-zinc-100 overflow-hidden flex items-end justify-center px-2 gap-0.5 mt-2">
                             @foreach([1,3,1,1,2,1,4,1,2,2,1,3,1,1,2,1,1,4,1,3,1,1,2] as $w)
                                 <div class="bg-zinc-900 h-full" style="width:{{ $w * 1.5 }}px"></div>
                             @endforeach
                         </div>
                     </div>
                </div>
            </div>
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
