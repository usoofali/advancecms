<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Find both roles
        $bookkeeperRole = Role::where('role_name', 'Bookkeeper')->first();
        $accountantRole = Role::where('role_name', 'Accountant')->first();

        if ($bookkeeperRole && $accountantRole) {
            // 2. Move all users who have Bookkeeper role to Accountant role
            // We use DB facade to update the pivot table directly
            DB::table('user_roles')
                ->where('role_id', $bookkeeperRole->role_id)
                ->update(['role_id' => $accountantRole->role_id]);

            // 3. Update staff table direct role_id reference if it exists
            DB::table('staff')
                ->where('role_id', $bookkeeperRole->role_id)
                ->update(['role_id' => $accountantRole->role_id]);

            // 4. Delete the Bookkeeper role
            $bookkeeperRole->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // To reverse, we would recreate the Bookkeeper role and theoretically move users back
        // but we can't cleanly identify who used to be a bookkeeper vs who was originally an accountant.
        // So we will just recreate the role and its core permissions.
        $role = Role::create([
            'role_name' => 'Bookkeeper',
            'description' => 'Financial record maintenance',
        ]);

        $permissions = Permission::whereIn('permission_name', ['view_payments', 'record_payments'])->pluck('permission_id');
        $role->permissions()->sync($permissions);
    }
};
