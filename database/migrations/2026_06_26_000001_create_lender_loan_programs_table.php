<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lender_loan_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
            $table->string('program_name');
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->decimal('points', 5, 2)->nullable();
            $table->decimal('max_ltc', 5, 2)->nullable();
            $table->decimal('max_ltv', 5, 2)->nullable();
            $table->string('term_length')->nullable();
            $table->decimal('purchase_closing_cost_percent', 5, 2)->nullable();
            $table->boolean('builders_risk_insurance')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'lender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lender_loan_programs');
    }
};
