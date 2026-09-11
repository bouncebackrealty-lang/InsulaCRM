<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedInteger('lead_number')->nullable()->after('tenant_id');
            $table->index(['tenant_id', 'lead_number']);
        });

        // Number only active leads so a tenant that has cleared its test data
        // starts its next lead at #1 instead of continuing from deleted rows.
        DB::table('leads')
            ->select('tenant_id')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('tenant_id')
            ->each(function ($tenantId): void {
                $number = 1;

                DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->pluck('id')
                    ->each(function ($leadId) use (&$number): void {
                        DB::table('leads')
                            ->where('id', $leadId)
                            ->update(['lead_number' => $number++]);
                    });
            });

        Schema::table('comparable_sales', function (Blueprint $table) {
            $table->decimal('original_list_price', 12, 2)->nullable()->after('sale_price');
            $table->unsignedInteger('days_on_market')->nullable()->after('sale_date');
        });
    }

    public function down(): void
    {
        Schema::table('comparable_sales', function (Blueprint $table) {
            $table->dropColumn(['original_list_price', 'days_on_market']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'lead_number']);
            $table->dropColumn('lead_number');
        });
    }
};
