<?php

namespace Database\Seeders;

use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class WebsiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'hero_title' => 'Welcome to Arise College of Health Sciences and Technology',
            'hero_subtitle' => 'Empowering the next generation of healthcare professionals with excellence, innovation, and compassion.',
            'about_text' => 'Arise College of Health Sciences and Technology is a premier institution dedicated to producing highly skilled and competent healthcare professionals. We offer a rigorous academic curriculum, state-of-the-art facilities, and practical clinical training to prepare our students for the dynamic world of healthcare.',
            'vision' => 'To be a globally recognized center of excellence in health sciences education, research, and community service, shaping the future of healthcare through innovation and leadership.',
            'mission' => 'Our mission is to provide outstanding health sciences education that fosters critical thinking, ethical practice, and clinical competence. We are committed to nurturing compassionate professionals who will improve health outcomes and serve their communities with integrity.',
            'contact_email' => 'info@arisecollege.edu.ng',
            'contact_phone' => '+234 800 000 0000',
            'address' => '123 Health Avenue, Medical District, City, State, Nigeria',
            'social_facebook' => 'https://facebook.com/arisecollege',
            'social_twitter' => 'https://twitter.com/arisecollege',
            'social_linkedin' => 'https://linkedin.com/school/arisecollege',
        ];

        foreach ($settings as $key => $value) {
            WebsiteSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
