<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehab_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('line_item');
            $table->string('category', 80);
            $table->decimal('budgeted_cost', 12, 2)->default(0);
            $table->unsignedInteger('estimated_duration_days')->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status', 30)->default('not_started');
            $table->timestamps();

            $table->index(['tenant_id', 'deal_id']);
            $table->index(['deal_id', 'status']);
            $table->index('contractor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rehab_line_items');
    }
};
