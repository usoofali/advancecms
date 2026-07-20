<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdCardTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'name',
        'type',
        'layout',
        'orientation',
        'primary_color',
        'secondary_color',
        'text_color',
        'accent_color',
        'header_text',
        'footer_text',
        'background_image_path',
        'font_family',
        'font_weight',
        'font_style',
        'text_align',
        'disclaimer_text',
        'show_signature_line',
        'back_background_color',
        'back_text_color',
        'show_qr',
        'show_barcode',
        'show_blood_group',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_qr' => 'boolean',
            'show_barcode' => 'boolean',
            'show_blood_group' => 'boolean',
            'show_signature_line' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function getBackgroundUrlAttribute(): ?string
    {
        return $this->background_image_path ? asset('storage/'.$this->background_image_path) : null;
    }
}
