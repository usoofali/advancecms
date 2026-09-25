<div class="min-h-screen flex flex-col justify-center items-center p-6 bg-gradient-to-br from-slate-50 via-emerald-50/50 to-teal-50/40 text-slate-800 relative selection:bg-emerald-500 selection:text-white">
    <!-- Ambient Background Lighting Accent -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-300/30 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-teal-300/30 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 w-full max-w-md bg-white/90 backdrop-blur-xl border border-slate-200/90 rounded-3xl p-8 shadow-2xl space-y-6">
        <!-- Logo & Header -->
        <div class="text-center space-y-2">
            <div class="inline-flex p-3 bg-gradient-to-tr from-emerald-600 via-teal-600 to-cyan-600 rounded-2xl shadow-lg shadow-emerald-600/20 mb-2">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V9a2 2 0 012-2h2a2 2 0 012 2v12m-6 0a2 2 0 002-2v-4a2 2 0 00-2-2h-2a2 2 0 00-2 2v4a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Central Hub Portal</h2>
            <p class="text-xs text-slate-500 font-medium leading-relaxed">Multi-Tenant Gateway & Cross-Instance Analytics Authentication</p>
        </div>

        <!-- Login Form -->
        <form wire:submit="login" class="space-y-4">
            <div>
                <label for="username" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Hub Admin Username</label>
                <input type="text" id="username" wire:model="username" placeholder="Enter Hub Username" class="w-full px-4 py-3 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl text-sm font-mono font-bold text-slate-900 placeholder-slate-400 transition">
                @error('username') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Hub Admin Password</label>
                <input type="password" id="password" wire:model="password" placeholder="••••••••••••" class="w-full px-4 py-3 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl text-sm font-mono font-bold text-slate-900 placeholder-slate-400 transition">
                @error('password') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 hover:from-emerald-500 hover:to-cyan-500 text-white font-extrabold text-sm rounded-xl shadow-lg shadow-emerald-700/20 transition duration-200 flex items-center justify-center gap-2 cursor-pointer">
                <svg wire:loading wire:target="login" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Sign In to Hub Gateway</span>
            </button>
        </form>

        <div class="pt-4 border-t border-slate-200/80 text-center">
            <p class="text-[11px] text-slate-500 font-medium">Secured Direct Database Multi-Tenant Architecture</p>
        </div>
    </div>
</div>
