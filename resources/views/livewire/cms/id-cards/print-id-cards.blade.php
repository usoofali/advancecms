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

    <div class="space-y-6 shrink-0 pb-12">
        @foreach($items as $item)
            @php
                if ($mode === 'requests') {
                    $user = $item->user;
                    $profile = ($type === 'student') ? $user?->student : $user?->staff;
                    $institution = $item->institution;
                } else {
                    $profile = $item;
                    $user = $item->user;
                    $institution = $item->institution;
                }
                
                $name = $user?->name ?? ($profile?->first_name . ' ' . $profile?->last_name);
                $idNumber = ($type === 'student') ? ($profile?->matric_number ?? 'N/A') : ($profile?->staff_number ?? 'N/A');
                $photo = ($profile?->photo_path) ? asset('storage/'.$profile->photo_path) : null;
                $phone = $profile?->phone ?? 'N/A';
                $email = ($type === 'staff') ? ($profile?->email ?? $user?->email) : ($user?->email ?? 'N/A');
                $dept = ($type === 'student') ? ($profile?->program?->department?->name ?? 'N/A') : ($profile?->designation ?? 'N/A');
                $qrData = route('home', ['verify_id' => $idNumber]);
            @endphp

            <div class="flex flex-col md:flex-row items-center justify-center gap-4 print:gap-2 print:mb-8 break-inside-avoid">
                <!-- FRONT SIDE -->
                <div class="id-card-side relative overflow-hidden bg-white shadow-xl print:shadow-none print:border print:border-zinc-300" style="width: 85.6mm; height: 53.98mm; border-radius: 3mm; font-family: 'Inter', sans-serif;">
                    <!-- Minimalist Header -->
                    <div class="relative z-10 flex items-center px-4 py-2 gap-2 bg-white/60 backdrop-blur-sm border-b border-zinc-100">
                        <div class="size-10 bg-white rounded-lg p-1 shadow-sm border border-zinc-50 flex items-center justify-center shrink-0">
                            @if($institution->logo_url)
                                <img src="{{ $institution->logo_url }}" class="w-full h-full object-contain">
                            @else
                                <flux:icon.building-library class="size-6 text-blue-600" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="text-[8pt] font-black text-zinc-900 uppercase leading-none break-words pr-2">{{ $institution->name }}</h2>
                            <p class="text-[6pt] font-black {{ $type === 'student' ? 'text-blue-600' : 'text-zinc-600' }} uppercase mt-0.5 tracking-wider">{{ $type === 'student' ? __('Student ID Card') : __('Staff Identity Card') }}</p>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="relative z-10 flex px-4 py-3 gap-4 items-center">
                        <div class="size-24 rounded-xl overflow-hidden border-2 border-white shadow-md bg-zinc-50 shrink-0">
                            @if($photo)
                                <img src="{{ $photo }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <flux:icon.user class="size-10 text-zinc-200" />
                                </div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <h3 class="text-[12pt] font-black text-zinc-900 truncate leading-tight uppercase">{{ $name }}</h3>
                            <p class="text-[9pt] font-bold text-blue-600 mb-2 truncate uppercase">{{ $dept }}</p>
                            
                            <div class="grid grid-cols-1 gap-1">
                                <div class="flex flex-col">
                                    <span class="text-[5.5pt] text-zinc-400 font-bold uppercase tracking-widest">{{ $type === 'student' ? __('Matric Number') : __('Staff Number') }}</span>
                                    <span class="text-[11pt] font-black text-zinc-800 tracking-wider font-mono">{{ $idNumber }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Side Accent -->
                    <div class="absolute right-0 top-0 h-full w-1 {{ $type === 'student' ? 'bg-blue-600' : 'bg-zinc-900' }}"></div>
                </div>

                <!-- BACK SIDE -->
                <div class="id-card-side relative overflow-hidden bg-white shadow-xl print:shadow-none print:border print:border-zinc-300" style="width: 85.6mm; height: 53.98mm; border-radius: 3mm; font-family: 'Inter', sans-serif;">
                     <div class="p-4 flex flex-col h-full bg-zinc-50/30">
                         <!-- Details Grid -->
                         <div class="flex justify-between items-start mb-3">
                             <div class="space-y-2">
                                 <div>
                                     <h4 class="text-[6pt] font-black text-zinc-400 uppercase tracking-widest mb-0.5">{{ __('Address') }}</h4>
                                     <p class="text-[7pt] font-bold text-zinc-700 leading-tight w-40 italic">{{ $institution->address ?: __('No address set.') }}</p>
                                 </div>
                                 <div class="grid grid-cols-1 gap-1">
                                     <div class="flex items-center gap-1.5 text-[7pt] font-bold text-zinc-800">
                                         <flux:icon.phone class="size-2 text-zinc-400" />
                                         <span>{{ $phone }}</span>
                                     </div>
                                     <div class="flex items-center gap-1.5 text-[7pt] font-bold text-zinc-800">
                                         <flux:icon.envelope class="size-2 text-zinc-400" />
                                         <span class="truncate w-32 lowercase">{{ $email }}</span>
                                     </div>
                                 </div>
                             </div>
                             <div class="bg-white p-1 rounded-lg border border-zinc-100 shadow-sm">
                                 <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={{ urlencode($qrData) }}" class="size-10">
                             </div>
                         </div>

                         <!-- Disclaimer -->
                         <div class="border-t border-dashed border-zinc-200 pt-2 flex-1">
                             <p class="text-[6pt] text-zinc-500 font-medium leading-normal italic">
                                 {{ __('This card is the property of :inst. If found, please return to any nearest police station or notify the institution.', ['inst' => $institution->name]) }}
                             </p>
                         </div>

                         <!-- Footer -->
                         <div class="flex items-end justify-between mt-2">
                             <div class="h-4 w-32 bg-zinc-100 rounded-sm overflow-hidden flex items-end px-1 gap-px">
                                 @foreach([1,3,1,2,1,1,4,1,2,1,2,1,3,1,1,2,1,4,1] as $w)
                                     <div class="bg-zinc-800 h-full" style="width:{{ $w }}px"></div>
                                 @endforeach
                             </div>
                             <div class="flex flex-col items-center">
                                 <div class="h-4 w-20 border-b border-zinc-300"></div>
                                 <span class="text-[5pt] font-bold text-zinc-400 uppercase mt-1">{{ __('Authorized Signature') }}</span>
                             </div>
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
