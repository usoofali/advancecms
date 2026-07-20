<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Title;
use App\Models\WebsiteSetting;
use Illuminate\Support\Facades\Storage;

new #[Title('Website Settings')] class extends Component {
    use WithFileUploads;

    public string $website_name = '';
    public string $hero_title = '';
    public string $hero_subtitle = '';
    public $hero_image_upload;
    public ?string $current_hero_image = null;
    
    public string $about_text = '';
    public string $vision = '';
    public string $mission = '';
    public string $contact_email = '';
    public string $contact_phone = '';
    public string $address = '';
    public string $social_facebook = '';
    public string $social_twitter = '';
    public string $social_linkedin = '';

    public function mount()
    {
        $settings = WebsiteSetting::pluck('value', 'key')->toArray();
        
        $this->website_name = $settings['website_name'] ?? 'Advance CMS';
        $this->hero_title = $settings['hero_title'] ?? 'Welcome to Advance CMS';
        $this->hero_subtitle = $settings['hero_subtitle'] ?? 'Manage your institution effectively.';
        $this->current_hero_image = $settings['hero_image'] ?? null;
        
        $this->about_text = $settings['about_text'] ?? '';
        $this->vision = $settings['vision'] ?? '';
        $this->mission = $settings['mission'] ?? '';
        $this->contact_email = $settings['contact_email'] ?? '';
        $this->contact_phone = $settings['contact_phone'] ?? '';
        $this->address = $settings['address'] ?? '';
        $this->social_facebook = $settings['social_facebook'] ?? '';
        $this->social_twitter = $settings['social_twitter'] ?? '';
        $this->social_linkedin = $settings['social_linkedin'] ?? '';
    }

    public function save()
    {
        $data = [
            'website_name' => $this->website_name,
            'hero_title' => $this->hero_title,
            'hero_subtitle' => $this->hero_subtitle,
            'about_text' => $this->about_text,
            'vision' => $this->vision,
            'mission' => $this->mission,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'social_facebook' => $this->social_facebook,
            'social_twitter' => $this->social_twitter,
            'social_linkedin' => $this->social_linkedin,
        ];

        if ($this->hero_image_upload) {
            $this->validate([
                'hero_image_upload' => 'image|max:5120', // 5MB max
            ]);
            
            $path = $this->hero_image_upload->store('website', 'public');
            $data['hero_image'] = '/storage/' . $path;
            $this->current_hero_image = $data['hero_image'];
            $this->hero_image_upload = null;
        } else {
            $data['hero_image'] = $this->current_hero_image;
        }

        foreach ($data as $key => $value) {
            WebsiteSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '']
            );
        }

        $this->dispatch('notify', message: __('Website settings saved successfully!'), variant: 'success');
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('Website Settings')" :subheading="__('Manage the content, text, and details displayed on the public landing pages.')">
        <div class="space-y-6">
            <form wire:submit="save" class="space-y-6">
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('General Identity') }}</flux:heading>
                    <div class="space-y-4">
                        <flux:input wire:model="website_name" :label="__('Website Name')" placeholder="Advance CMS" />
                    </div>
                </flux:card>

                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Hero Section (Home Page)') }}</flux:heading>
                    <div class="space-y-4">
                        <flux:input wire:model="hero_title" :label="__('Hero Title')" placeholder="Welcome to Advance CMS" />
                        <flux:textarea wire:model="hero_subtitle" :label="__('Hero Subtitle')" placeholder="Manage your institution effectively." />
                        
                        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                            <flux:heading size="sm" class="mb-2">{{ __('Background Image / Slider') }}</flux:heading>
                            <div class="flex flex-col sm:flex-row gap-6 items-start">
                                <div class="flex-shrink-0 flex flex-col items-center gap-2">
                                    <div class="w-48 h-32 rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 flex items-center justify-center bg-zinc-50 dark:bg-zinc-900 overflow-hidden relative">
                                        @if($hero_image_upload)
                                            <img src="{{ $hero_image_upload->temporaryUrl() }}" class="w-full h-full object-cover" />
                                        @elseif($current_hero_image)
                                            <img src="{{ $current_hero_image }}" class="w-full h-full object-cover" />
                                        @else
                                            <flux:icon.photo class="size-8 text-zinc-300 dark:text-zinc-600" />
                                        @endif
                                    </div>
                                    @if($current_hero_image && !$hero_image_upload)
                                        <flux:button wire:click="$set('current_hero_image', null); $set('hero_image_upload', null)" size="xs" variant="danger" icon="trash">
                                            {{ __('Remove Image') }}</flux:button>
                                    @endif
                                </div>

                                <div class="flex-1 space-y-4 w-full">
                                    <flux:field>
                                        <flux:label>{{ __('Upload Hero Image') }}</flux:label>
                                        <flux:input type="file" wire:model="hero_image_upload" accept="image/*" />
                                        <flux:error name="hero_image_upload" />
                                        <flux:description>
                                            {{ __('Recommended size: 1920x1080px. Max: 5MB.') }}
                                        </flux:description>
                                    </flux:field>
                                </div>
                            </div>
                        </div>
                    </div>
                </flux:card>

                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('About Section') }}</flux:heading>
                    <div class="space-y-4">
                        <flux:textarea wire:model="about_text" rows="5" :label="__('About Text')" placeholder="Information about the institution..." />
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <flux:textarea wire:model="vision" rows="3" :label="__('Vision')" placeholder="Our vision is to..." />
                            <flux:textarea wire:model="mission" rows="3" :label="__('Mission')" placeholder="Our mission is to..." />
                        </div>
                    </div>
                </flux:card>

                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Contact Information') }}</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:input wire:model="contact_email" type="email" :label="__('Contact Email')" />
                        <flux:input wire:model="contact_phone" :label="__('Contact Phone')" />
                        <flux:textarea wire:model="address" class="md:col-span-2" :label="__('Physical Address')" />
                    </div>
                </flux:card>

                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Social Media Links') }}</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <flux:input wire:model="social_facebook" :label="__('Facebook URL')" placeholder="https://facebook.com/..." />
                        <flux:input wire:model="social_twitter" :label="__('Twitter (X) URL')" placeholder="https://twitter.com/..." />
                        <flux:input wire:model="social_linkedin" :label="__('LinkedIn URL')" placeholder="https://linkedin.com/..." />
                    </div>
                </flux:card>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Save Settings') }}</flux:button>
                </div>
            </form>
        </div>
    </x-pages::settings.layout>
</section>
