<?php

namespace App\Models;

use Database\Factories\GradingSystemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradingSystem extends Model
{
    /** @use HasFactory<GradingSystemFactory> */
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'name',
        'scale',
    ];

    protected function casts(): array
    {
        return [
            'scale' => 'array',
        ];
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
