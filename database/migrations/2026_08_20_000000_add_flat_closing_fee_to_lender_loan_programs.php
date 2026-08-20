<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lender_loan_programs', function (Blueprint $table) {
            $table->string('purchase_closing_cost_type', 20)
                ->default('percentage')
                ->after('purchase_closing_cost_percent');
            $table->decimal('purchase_closing_cost_flat_fee', 12, 2)
                ->nullable()
                ->after('purchase_closing_cost_type');
        });
    }

    public function down(): void
    {
        Schema::table('lender_loan_programs', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_closing_cost_type',
                'purchase_closing_cost_flat_fee',
            ]);
        });
    }
};
