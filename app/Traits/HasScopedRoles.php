<?php

namespace App\Traits;

use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait HasScopedRoles
{
    /**
     * Assign a scoped role to this user for a specific model.
     */
    public function assignScopedRole(string $roleName, Model $model): void
    {
        // Security Constraint: A user with a primary identity of 'Student'
        // cannot be assigned any administrative scoped roles.
        if ($this->hasRole('Student') && $roleName !== 'Student') {
            throw new AuthorizationException(
                "Users with a primary 'Student' identity cannot be assigned the '{$roleName}' scoped role."
            );
        }

        $role = Role::where('role_name', $roleName)->firstOrFail();

        DB::table('model_user_roles')->updateOrInsert(
            [
                'user_id' => $this->id,
                'role_id' => $role->role_id,
                'model_type' => $model->getMorphClass(),
                'model_id' => $model->getKey(),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Remove a scoped role from this user for a specific model.
     */
    public function removeScopedRole(string $roleName, Model $model): void
    {
        $role = Role::where('role_name', $roleName)->first();

        if ($role) {
            DB::table('model_user_roles')
                ->where('user_id', $this->id)
                ->where('role_id', $role->role_id)
                ->where('model_type', $model->getMorphClass())
                ->where('model_id', $model->getKey())
                ->delete();
        }
    }

    /**
     * Check if the user has a specific role scoped to a specific model.
     */
    public function hasScopedRole(string $roleName, Model $model): bool
    {
        $role = Role::where('role_name', $roleName)->first();

        if (! $role) {
            return false;
        }

        return DB::table('model_user_roles')
            ->where('user_id', $this->id)
            ->where('role_id', $role->role_id)
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->exists();
    }

    /**
     * Get all models of a specific class where this user has the given role.
     * Returns a collection of model IDs.
     */
    public function getScopedModelIds(string $roleName, string $modelClass): array
    {
        $role = Role::where('role_name', $roleName)->first();

        if (! $role) {
            return [];
        }

        $morphClass = (new $modelClass)->getMorphClass();

        return DB::table('model_user_roles')
            ->where('user_id', $this->id)
            ->where('role_id', $role->role_id)
            ->where('model_type', $morphClass)
            ->pluck('model_id')
            ->toArray();
    }

    /**
     * Check if the user has a specific permission through any of their scoped role
     * assignments. This enables contextual privilege elevation: a Lecturer assigned
     * the "Accountant" role for one department gains invoice access for that dept only.
     *
     * Uses a per-request static cache to avoid N+1 gate checks across @can() calls.
     */
    public function hasScopedPermission(string $permission): bool
    {
        static $cache = [];

        $cacheKey = $this->id.'|'.$permission;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $scopedRoleIds = DB::table('model_user_roles')
            ->where('user_id', $this->id)
            ->distinct()
            ->pluck('role_id');

        if ($scopedRoleIds->isEmpty()) {
            return $cache[$cacheKey] = false;
        }

        $hasPermission = DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.permission_id')
            ->whereIn('role_permissions.role_id', $scopedRoleIds)
            ->where('permissions.permission_name', $permission)
            ->exists();

        return $cache[$cacheKey] = $hasPermission;
    }

    /**
     * Get all distinct scoped role names assigned to this user (across all models).
     * Useful for sidebar visibility and contextual UI decisions.
     *
     * @return array<string>
     */
    public function getAllScopedRoleNames(): array
    {
        static $cache = [];

        $cacheKey = 'scoped_roles_'.$this->id;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        return $cache[$cacheKey] = DB::table('model_user_roles')
            ->join('roles', 'model_user_roles.role_id', '=', 'roles.role_id')
            ->where('model_user_roles.user_id', $this->id)
            ->distinct()
            ->pluck('roles.role_name')
            ->toArray();
    }
}
