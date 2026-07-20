<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'address',
        'city',
        'state',
        'country',
        'contact_person',
        'phone',
        'email',
        'website',
        'capacity',
        'active_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'active_status' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    public function placements()
    {
        return $this->hasMany(StudentPlacement::class);
    }
}
