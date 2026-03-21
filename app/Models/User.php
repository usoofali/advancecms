<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Get the roles associated with the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where(fn ($q) => $q->where('role_name', $role))->exists();
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->where(fn ($q) => $q->whereIn('role_name', $roles))->exists();
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermissionTo(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('permission_name', $permission);
        })->exists();
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'email', 'email');
    }

    /**
     * Get the staff profile associated with the user.
     */
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class, 'email', 'email');
    }

    /**
     * Check if the user's account is active based on their linked profile.
     */
    public function getStatus(): string
    {
        if ($this->hasRole('Student')) {
            return $this->student?->status ?? 'inactive';
        }

        if ($this->hasRole('Staff')) {
            return $this->staff?->status ?? 'inactive';
        }

        return 'active'; // Admin/others default to active
    }

    public function isActive(): bool
    {
        return $this->getStatus() === 'active';
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
