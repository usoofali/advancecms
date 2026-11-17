<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumns('payroll_staff_profiles', ['bank_name', 'account_number', 'account_name'])) {
            $profiles = DB::table('payroll_staff_profiles')
                ->where(function ($q) {
                    $q->whereNotNull('bank_name')
                        ->orWhereNotNull('account_number')
                        ->orWhereNotNull('account_name');
                })
                ->get(['staff_id', 'bank_name', 'account_number', 'account_name']);

            foreach ($profiles as $profile) {
                $staff = DB::table('staff')->where('id', $profile->staff_id)->first(['bank_name', 'account_number', 'account_name']);
                if ($staff) {
                    DB::table('staff')->where('id', $profile->staff_id)->update([
                        'bank_name' => $staff->bank_name ?: $profile->bank_name,
                        'account_number' => $staff->account_number ?: $profile->account_number,
                        'account_name' => $staff->account_name ?: $profile->account_name,
                    ]);
                }
            }

            Schema::table('payroll_staff_profiles', function (Blueprint $table) {
                $table->dropColumn(['bank_name', 'account_number', 'account_name']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payroll_staff_profiles', 'bank_name')) {
            Schema::table('payroll_staff_profiles', function (Blueprint $table) {
                $table->string('bank_name')->nullable()->after('custom_rate');
                $table->string('account_number')->nullable()->after('bank_name');
                $table->string('account_name')->nullable()->after('account_number');
            });
        }
    }
};
