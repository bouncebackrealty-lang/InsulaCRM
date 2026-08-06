<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    public function up(): void
    {
        $wholesaleTenantIds = DB::table('tenants')
            ->where('business_mode', 'wholesale')
            ->pluck('id');

        if ($wholesaleTenantIds->isEmpty()) {
            return;
        }

        DB::table('deals')
            ->whereIn('tenant_id', $wholesaleTenantIds)
            ->whereNull('inspection_period_days')
            ->update([
                'inspection_period_days' => 10,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {

    }
};
