<?php

namespace App\Models;

use App\Notifications\WelcomeNotification;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function ($staff) {
            if (! $staff->staff_number) {
                $year = date('Y');
                $random = strtoupper(Str::random(6));
                $staff->staff_number = sprintf('STF/%s/%s', $year, $random);
            }

            if (User::where('email', '=', $staff->email, 'and')->exists()) {
                throw new \Exception(__('A user with this email already exists.'));
            }

            // Auto-assign institutional default allowance for lecturers
            if ($staff->role_id == 4) { // 4 is the Lecturer role ID
                $institution = Institution::find($staff->institution_id);
                if ($institution) {
                    $staff->attendance_allowance = $institution->default_allowance;
                }
            }
        });

        static::saved(function ($staff) {
            $user = User::where('email', '=', $staff->email, 'and')->first();

            if (! $user) {
                // Generate a default password for initial login
                $password = '12345678';

                $user = User::create([
                    'email' => $staff->email,
                    'name' => "{$staff->first_name} {$staff->last_name}",
                    'institution_id' => $staff->institution_id,
                    'password' => Hash::make($password),
                ]);

                $user->notify(new WelcomeNotification($password));
            } else {
                $user->update([
                    'name' => "{$staff->first_name} {$staff->last_name}",
                    'institution_id' => $staff->institution_id,
                ]);
            }

            if ($staff->role_id) {
                $user->roles()->sync([$staff->role_id]);
            }
        });

        static::deleting(function ($staff) {
            $user = User::where('email', '=', $staff->email, 'and')->first();

            if ($user) {
                // Remove all roles attached to this user
                $user->roles()->sync([]);

                // Delete the user account
                $user->delete();
            }
        });
    }

    protected $fillable = [
        'institution_id',
        'role_id',
        'staff_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'bank_name',
        'account_number',
        'account_name',
        'designation',
        'attendance_allowance',
        'photo_path',
        'status',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    public function attendancePayments(): HasMany
    {
        return $this->hasMany(AttendancePayment::class, 'staff_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function hodDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'hod_id');
    }
}
