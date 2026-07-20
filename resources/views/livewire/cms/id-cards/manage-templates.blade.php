<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Manage ID Card Templates') }}</flux:heading>
            <flux:subheading>{{ __('Create and configure dynamic ID card templates for institutions') }}
            </flux:subheading>
        </div>
        @if(!$showForm)
            <flux:button wire:click="createTemplate" variant="primary" icon="plus">
                {{ __('Create Template') }}
            </flux:button>
        @endif
    </div>

    @if(!$showForm)
        <flux:card>
            <div class="flex flex-wrap items-center gap-4 mb-4">
                @if(auth()->user()->hasRole('Super Admin'))
                    <div class="w-full sm:w-64">
                        <flux:select wire:model.live="institution_id" :placeholder="__('All Institutions')">
                            <flux:select.option value="">{{ __('System Defaults') }}</flux:select.option>
                            @foreach($institutions as $inst)
                                <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif
                <div class="w-full sm:w-48">
                    <flux:select wire:model.live="type">
                        <flux:select.option value="both">{{ __('All Types') }}</flux:select.option>
                        <flux:select.option value="student">{{ __('Student') }}</flux:select.option>
                        <flux:select.option value="staff">{{ __('Staff') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-zinc-500 dark:text-zinc-400">
                    <thead class="text-xs uppercase bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                        <tr>
                            <th scope="col" class="px-6 py-3">{{ __('Template Name') }}</th>
                            <th scope="col" class="px-6 py-3">{{ __('Institution') }}</th>
                            <th scope="col" class="px-6 py-3">{{ __('Type / Layout') }}</th>
                            <th scope="col" class="px-6 py-3">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $template)
                            <tr class="border-b dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white flex items-center gap-3">
                                    <div class="w-4 h-4 rounded-full border border-zinc-200 dark:border-zinc-700 shadow-sm"
                                        style="background-color: {{ $template->primary_color }}"></div>
                                    {{ $template->name }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($template->institution_id)
                                        <flux:badge size="sm" color="zinc">{{ $template->institution->name }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="blue">{{ __('System Default') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <flux:badge size="sm"
                                            color="{{ $template->type === 'student' ? 'green' : ($template->type === 'staff' ? 'orange' : 'indigo') }}">
                                            {{ ucfirst($template->type) }}
                                        </flux:badge>
                                        <span
                                            class="text-xs text-zinc-400">{{ ucfirst(str_replace('_', ' ', $template->layout)) }}
                                            ({{ ucfirst($template->orientation) }})</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($template->is_active)
                                        <flux:badge size="sm" color="green" icon="check-circle">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red" icon="x-circle">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <flux:button wire:click="editTemplate({{ $template->id }})" size="sm" variant="ghost"
                                        icon="pencil-square" />
                                    <flux:button wire:click="deleteTemplate({{ $template->id }})"
                                        wire:confirm="Are you sure you want to delete this template?" size="sm" variant="ghost"
                                        icon="trash" class="text-red-500 hover:text-red-700" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                    {{ __('No ID card templates found. Create one to get started.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $templates->links() }}
            </div>
        </flux:card>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Form Configuration -->
            <div class="lg:col-span-7 space-y-6">
                <flux:card>
                    <form wire:submit="saveTemplate" class="space-y-6">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input wire:model="form_name" :label="__('Template Name')"
                                placeholder="e.g. Executive Staff Card" required />

                            @if(auth()->user()->hasRole('Super Admin'))
                                <flux:select wire:model="form_institution_id" :label="__('Assign to Institution')">
                                    <flux:select.option value="">{{ __('System Default (Applies to all)') }}
                                    </flux:select.option>
                                    @foreach($institutions as $inst)
                                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:input type="text" :label="__('Assigned Institution')"
                                    value="{{ auth()->user()->institution?->name }}" disabled />
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <flux:select wire:model.live="form_type" :label="__('Card Type')">
                                <flux:select.option value="student">{{ __('Student') }}</flux:select.option>
                                <flux:select.option value="staff">{{ __('Staff') }}</flux:select.option>
                                <flux:select.option value="both">{{ __('Both') }}</flux:select.option>
                            </flux:select>

                            <flux:select wire:model.live="form_layout" :label="__('Layout Style')">
                                <flux:select.option value="classic">{{ __('Classic (Top Banner)') }}</flux:select.option>
                                <flux:select.option value="modern_sidebar">{{ __('Modern (Left Sidebar)') }}
                                </flux:select.option>
                                <flux:select.option value="minimal">{{ __('Minimal') }}</flux:select.option>
                            </flux:select>

                            <flux:select wire:model.live="form_orientation" :label="__('Orientation')">
                                <flux:select.option value="horizontal">{{ __('Horizontal') }}</flux:select.option>
                                <flux:select.option value="vertical">{{ __('Vertical') }}</flux:select.option>
                            </flux:select>
                        </div>

                        <flux:separator />

                        <flux:heading size="lg">{{ __('Colors & Branding') }}</flux:heading>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <flux:field>
                                <flux:label>{{ __('Primary Color') }}</flux:label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="form_primary_color"
                                        class="h-9 w-9 p-0.5 rounded border border-zinc-200 cursor-pointer">
                                    <flux:input wire:model.live="form_primary_color" class="flex-1" />
                                </div>
                                <flux:error name="form_primary_color" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Secondary Color') }}</flux:label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="form_secondary_color"
                                        class="h-9 w-9 p-0.5 rounded border border-zinc-200 cursor-pointer">
                                    <flux:input wire:model.live="form_secondary_color" class="flex-1" />
                                </div>
                                <flux:error name="form_secondary_color" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Text Color') }}</flux:label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="form_text_color"
                                        class="h-9 w-9 p-0.5 rounded border border-zinc-200 cursor-pointer">
                                    <flux:input wire:model.live="form_text_color" class="flex-1" />
                                </div>
                                <flux:error name="form_text_color" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Accent Color') }}</flux:label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="form_accent_color"
                                        class="h-9 w-9 p-0.5 rounded border border-zinc-200 cursor-pointer">
                                    <flux:input wire:model.live="form_accent_color" class="flex-1" />
                                </div>
                                <flux:error name="form_accent_color" />
                            </flux:field>
                        </div>

                        <flux:separator />

                        <flux:heading size="lg">{{ __('Text & Images') }}</flux:heading>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input wire:model.live="form_header_text" :label="__('Header Text (Optional)')"
                                placeholder="STUDENT IDENTITY CARD" />
                            <flux:input wire:model.live="form_footer_text" :label="__('Footer Text (Optional)')"
                                placeholder="If found, please return..." />
                        </div>

                        <div class="flex flex-col gap-3">
                            <flux:input type="file" wire:model="form_background_image" accept="image/*"
                                :label="__('Background Watermark / Image (Optional)')" />
                            @if($existing_background_url)
                                <div class="flex items-center gap-3">
                                    <img src="{{ $existing_background_url }}"
                                        class="h-16 w-16 object-cover rounded shadow-sm border border-zinc-200" />
                                    <span class="text-sm text-zinc-500">{{ __('Current background image') }}</span>
                                </div>
                            @endif
                        </div>

                        <flux:separator />

                        <flux:heading size="lg">{{ __('Typography (Text Styling)') }}</flux:heading>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <flux:select wire:model.live="form_font_family" :label="__('Font Family')">
                                <flux:select.option value="Inter, sans-serif">{{ __('Inter (Sans)') }}</flux:select.option>
                                <flux:select.option value="Roboto, sans-serif">{{ __('Roboto (Sans)') }}
                                </flux:select.option>
                                <flux:select.option value="'Times New Roman', serif">{{ __('Times New Roman (Serif)') }}
                                </flux:select.option>
                                <flux:select.option value="Arial, sans-serif">{{ __('Arial (Sans)') }}</flux:select.option>
                                <flux:select.option value="monospace">{{ __('Monospace') }}</flux:select.option>
                            </flux:select>

                            <flux:select wire:model.live="form_font_weight" :label="__('Font Weight (Name)')">
                                <flux:select.option value="normal">{{ __('Normal') }}</flux:select.option>
                                <flux:select.option value="font-semibold">{{ __('Semi-Bold') }}</flux:select.option>
                                <flux:select.option value="font-bold">{{ __('Bold') }}</flux:select.option>
                                <flux:select.option value="font-extrabold">{{ __('Extra Bold') }}</flux:select.option>
                            </flux:select>

                            <flux:select wire:model.live="form_font_style" :label="__('Font Style')">
                                <flux:select.option value="normal">{{ __('Normal') }}</flux:select.option>
                                <flux:select.option value="italic">{{ __('Italic') }}</flux:select.option>
                            </flux:select>

                            <flux:select wire:model.live="form_text_align" :label="__('Text Alignment')">
                                <flux:select.option value="left">{{ __('Left') }}</flux:select.option>
                                <flux:select.option value="center">{{ __('Center') }}</flux:select.option>
                                <flux:select.option value="right">{{ __('Right') }}</flux:select.option>
                            </flux:select>
                        </div>

                        <flux:separator />

                        <flux:heading size="lg">{{ __('Back Side Configuration') }}</flux:heading>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:field>
                                <flux:label>{{ __('Back Background Color') }}</flux:label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="form_back_background_color"
                                        class="h-9 w-9 p-0.5 rounded border border-zinc-200 cursor-pointer">
                                    <flux:input wire:model.live="form_back_background_color" class="flex-1" />
                                </div>
                                <flux:error name="form_back_background_color" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Back Text Color') }}</flux:label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="form_back_text_color"
                                        class="h-9 w-9 p-0.5 rounded border border-zinc-200 cursor-pointer">
                                    <flux:input wire:model.live="form_back_text_color" class="flex-1" />
                                </div>
                                <flux:error name="form_back_text_color" />
                            </flux:field>
                        </div>

                        <flux:textarea wire:model.live="form_disclaimer_text" :label="__('Disclaimer Text (Back)')" rows="3"
                            placeholder="This card is the property of..." />

                        <flux:separator />

                        <flux:heading size="lg">{{ __('Elements & Toggles') }}</flux:heading>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <flux:switch wire:model.live="form_show_qr" :label="__('Show QR Code')" />
                            <flux:switch wire:model.live="form_show_barcode" :label="__('Show Barcode')" />
                            <flux:switch wire:model.live="form_show_blood_group" :label="__('Show Blood Group')" />
                            <flux:switch wire:model.live="form_show_signature_line"
                                :label="__('Show Signature Line (Back)')" />
                            <flux:switch wire:model="form_is_active" :label="__('Template is Active')" />
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t dark:border-zinc-700">
                            <flux:button wire:click="cancelForm" variant="ghost">{{ __('Cancel') }}</flux:button>
                            <flux:button type="submit" variant="primary" icon="check">{{ __('Save Template') }}
                            </flux:button>
                        </div>
                    </form>
                </flux:card>
            </div>

            <!-- Live Preview -->
            <div class="lg:col-span-5 relative" x-data="{ side: 'front' }">
                <div class="sticky top-6">
                    <div class="flex justify-center gap-2 mb-4">
                        <button type="button" @click="side = 'front'"
                            :class="side === 'front' ? 'bg-zinc-800 text-white' : 'bg-white text-zinc-600'"
                            class="px-4 py-1.5 rounded-full text-sm font-medium border border-zinc-200 shadow-sm transition-colors">{{ __('Front') }}</button>
                        <button type="button" @click="side = 'back'"
                            :class="side === 'back' ? 'bg-zinc-800 text-white' : 'bg-white text-zinc-600'"
                            class="px-4 py-1.5 rounded-full text-sm font-medium border border-zinc-200 shadow-sm transition-colors">{{ __('Back') }}</button>
                    </div>
                    <flux:card
                        class="bg-zinc-100 dark:bg-zinc-900 border-dashed border-2 flex items-center justify-center p-8 min-h-[400px]">

                        <div class="relative overflow-hidden shadow-xl transition-all duration-300 ring-1 ring-zinc-200/50"
                            style="width: {{ $form_orientation === 'horizontal' ? '324px' : '204px' }}; height: {{ $form_orientation === 'horizontal' ? '204px' : '324px' }}; font-family: {{ $form_font_family }}; {{ $form_font_style === 'italic' ? 'font-style: italic;' : '' }}">

                            <!-- FRONT SIDE -->
                            <div x-show="side === 'front'" class="w-full h-full bg-white relative">
                                <!-- Classic Layout -->
                                @if($form_layout === 'classic')
                                    <div class="w-full h-12 flex flex-col justify-center items-center px-4 shrink-0 shadow-sm"
                                        style="background-color: {{ $form_primary_color }}; color: {{ $form_text_color }};">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-center w-full truncate">
                                            {{ $form_header_text ?: 'INSTITUTION NAME' }}</div>
                                    </div>
                                    <div class="p-3 flex gap-3 h-full relative z-10"
                                        style="flex-direction: {{ $form_orientation === 'horizontal' ? 'row' : 'column' }}; align-items: {{ $form_orientation === 'horizontal' ? 'flex-start' : 'center' }}">
                                        <!-- Photo -->
                                        <div class="bg-zinc-200 shrink-0 border-2"
                                            style="border-color: {{ $form_accent_color }}; width: 75px; height: 90px;"></div>
                                        <!-- Details -->
                                        <div class="flex-1 flex flex-col text-zinc-900 w-full"
                                            style="align-items: {{ $form_orientation === 'horizontal' ? 'flex-start' : 'center' }}; text-align: {{ $form_text_align }};">
                                            <div class="text-sm uppercase text-zinc-800 {{ $form_font_weight }}">JOHN DOE</div>
                                            <div class="text-[10px] font-semibold text-zinc-600 mt-1"
                                                style="color: {{ $form_secondary_color }}">COMPUTER SCIENCE</div>
                                            <div class="text-[10px] mt-0.5">ID: STU/2026/00121</div>

                                            @if($form_show_blood_group)
                                                <div class="mt-1 mb-2 flex gap-1 items-center"
                                                    style="justify-content: {{ $form_text_align === 'center' ? 'center' : ($form_text_align === 'right' ? 'flex-end' : 'flex-start') }}">
                                                    <span
                                                        class="text-[8px] font-bold px-1.5 py-0.5 rounded text-white bg-red-600 not-italic">O+</span>
                                                </div>
                                            @endif
                                        </div>

                                        @if($form_show_qr)
                                            <div
                                                class="mb-16 absolute bottom-2 right-2 w-12 h-12 bg-white p-1 shadow-sm border border-zinc-200">
                                                <div class="w-full h-full bg-zinc-800"></div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="absolute bottom-0 w-full p-1.5 text-center text-[7px]"
                                        style="background-color: {{ $form_secondary_color }}; color: {{ $form_text_color }};">
                                        {{ $form_footer_text ?: 'Official Identity Card' }}
                                    </div>
                                @endif

                                <!-- Modern Sidebar Layout -->
                                @if($form_layout === 'modern_sidebar')
                                    <div class="flex h-full w-full">
                                        <div class="h-full w-8 shrink-0 flex items-center justify-center shadow-md relative z-20"
                                            style="background-color: {{ $form_primary_color }}; color: {{ $form_text_color }};">
                                            <div
                                                class="-rotate-90 text-[8px] font-bold uppercase tracking-[0.2em] whitespace-nowrap">
                                                {{ $form_header_text ?: 'IDENTITY CARD' }}</div>
                                        </div>
                                        <div class="flex-1 p-3 relative flex gap-3 z-10"
                                            style="flex-direction: {{ $form_orientation === 'horizontal' ? 'row' : 'column' }}; align-items: {{ $form_orientation === 'horizontal' ? 'flex-start' : 'center' }}">
                                            <!-- Photo -->
                                            <div class="bg-zinc-200 shrink-0 border border-zinc-300 shadow-sm rounded-sm"
                                                style="width: 75px; height: 90px;"></div>
                                            <!-- Details -->
                                            <div class="flex-1 flex flex-col text-zinc-900 h-full w-full"
                                                style="align-items: {{ $form_orientation === 'horizontal' ? 'flex-start' : 'center' }}; text-align: {{ $form_text_align }};">
                                                <div
                                                    class="text-sm uppercase text-zinc-800 tracking-tight {{ $form_font_weight }}">
                                                    JOHN DOE</div>
                                                <div class="text-[9px] font-bold mt-1 uppercase"
                                                    style="color: {{ $form_primary_color }}">COMPUTER SCIENCE</div>
                                                <div class="text-[9px] mt-0.5 text-zinc-500">ID: STU/2026/001</div>

                                                <div class="mt-auto text-[7px] text-zinc-400 max-w-[80%] leading-tight"
                                                    style="text-align: {{ $form_text_align }};">
                                                    {{ $form_footer_text ?: 'Property of the institution.' }}
                                                </div>
                                            </div>

                                            @if($form_show_qr)
                                                <div
                                                    class="absolute bottom-3 right-3 w-12 h-12 bg-white p-1 shadow-sm border border-zinc-200 rounded">
                                                    <div class="w-full h-full bg-zinc-800"></div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                <!-- Minimal Layout -->
                                @if($form_layout === 'minimal')
                                    <div class="w-full h-1" style="background-color: {{ $form_primary_color }};"></div>
                                    <div class="flex h-full w-full p-4 relative z-10 gap-3"
                                        style="flex-direction: {{ $form_orientation === 'horizontal' ? 'row' : 'column' }}; align-items: {{ $form_orientation === 'horizontal' ? 'center' : 'center' }}">
                                        <!-- Photo -->
                                        <div class="bg-zinc-200 shrink-0 border border-zinc-300 rounded-lg shadow-sm"
                                            style="width: 75px; height: 90px;"></div>
                                        <!-- Details -->
                                        <div class="flex-1 flex flex-col text-zinc-900 w-full"
                                            style="align-items: {{ $form_orientation === 'horizontal' ? 'flex-start' : 'center' }}; text-align: {{ $form_text_align }};">
                                            <div class="text-[9px] font-semibold text-zinc-500 uppercase tracking-wider mb-1">
                                                {{ $form_header_text ?: 'IDENTIFICATION' }}</div>
                                            <div class="text-sm uppercase text-zinc-900 {{ $form_font_weight }}">JOHN DOE</div>
                                            <div class="text-[10px] mt-0.5 text-zinc-600">COMPUTER SCIENCE</div>

                                            <div class="mt-3 py-1 px-2 rounded bg-zinc-100 border border-zinc-200 w-fit"
                                                style="align-self: {{ $form_text_align === 'center' ? 'center' : ($form_text_align === 'right' ? 'flex-end' : 'flex-start') }}">
                                                <span class="text-[9px] font-mono text-zinc-700 not-italic">STU/2026/001</span>
                                            </div>
                                        </div>

                                        @if($form_show_qr)
                                            <div
                                                class="absolute bottom-4 right-4 w-10 h-10 bg-white shadow-sm border border-zinc-200 rounded-md overflow-hidden">
                                                <div class="w-full h-full bg-zinc-800"></div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- BACK SIDE -->
                            <div x-show="side === 'back'"
                                style="display: none; background-color: {{ $form_back_background_color }}; color: {{ $form_back_text_color }};"
                                class="w-full h-full relative p-4 flex flex-col {{ $form_orientation === 'horizontal' ? 'justify-between' : 'justify-center items-center gap-4' }}">
                                <div class="text-center w-full">
                                    <div class="font-bold text-[10px] uppercase mb-1">{{ __('Disclaimer') }}</div>
                                    <div class="text-[8px] leading-tight" style="text-align: {{ $form_text_align }};">
                                        {{ $form_disclaimer_text ?: 'This card remains the property of the issuing institution. If found, please return it to the nearest police station or the address listed below.' }}
                                    </div>
                                </div>

                                @if($form_show_signature_line)
                                    <div class="w-full flex justify-end mt-4">
                                        <div class="flex flex-col items-center">
                                            <div class="w-24 border-b border-current mb-1 border-opacity-50"></div>
                                            <div class="text-[6px] uppercase tracking-wide opacity-75">
                                                {{ __('Authorized Signature') }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if($form_show_barcode)
                                    <div class="w-full flex justify-center mt-auto">
                                        <div
                                            class="w-32 h-8 bg-zinc-800/10 dark:bg-zinc-200/10 rounded flex items-center justify-center border border-current border-opacity-20 relative overflow-hidden">
                                            <div class="w-full h-full opacity-30 px-1 flex flex-col justify-between py-1">
                                                <div class="w-full h-[1px] bg-current"></div>
                                                <div class="w-full h-[2px] bg-current"></div>
                                                <div class="w-full h-[1px] bg-current"></div>
                                                <div class="w-[80%] h-[2px] bg-current"></div>
                                            </div>
                                            <span
                                                class="absolute inset-0 flex items-center justify-center text-[8px] tracking-widest font-mono">123456789</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="absolute -bottom-8 left-0 right-0 text-center text-xs text-zinc-500">
                            {{ __('Live Preview (Actual print resolution is higher)') }}
                        </div>
                    </flux:card>
                </div>
            </div>
        </div>
    @endif
</div>