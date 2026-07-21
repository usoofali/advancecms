<?php

use App\Models\WebsiteSetting;

it('returns public website settings with dynamic theme config and app name fallback', function () {
    WebsiteSetting::updateOrCreate(
        ['key' => 'hero_title'],
        ['value' => 'Welcome to CSHT Gusau']
    );

    $response = $this->getJson('/api/public/website-settings');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'hero_title',
            'website_name',
            'theme' => [
                'accent',
                'accent_content',
                'accent_foreground',
            ],
        ])
        ->assertJson([
            'hero_title' => 'Welcome to CSHT Gusau',
            'website_name' => config('app.name'),
        ]);
});
