<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_lenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lender_loan_program_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('inquired');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['deal_id', 'lender_loan_program_id']);
            $table->index(['deal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_lenders');
    }
};
