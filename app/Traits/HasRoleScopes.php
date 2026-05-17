<?php

namespace App\Traits;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasRoleScopes
{
    /**
     * Get all users who have a specific scoped role for this model.
     */
    public function usersWithScopedRole(string $roleName): Collection
    {
        $role = Role::where('role_name', $roleName)->first();

        if (! $role) {
            return collect();
        }

        $userIds = DB::table('model_user_roles')
            ->where('role_id', $role->role_id)
            ->where('model_type', $this->getMorphClass())
            ->where('model_id', $this->getKey())
            ->pluck('user_id');

        return User::whereIn('id', $userIds)->get();
    }
}
