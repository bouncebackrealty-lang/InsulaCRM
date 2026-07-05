<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('deal_type', 50)->nullable()->after('stage');
            $table->boolean('is_priority')->default(false)->after('deal_type');
            $table->index(['tenant_id', 'deal_type']);
            $table->index(['tenant_id', 'is_priority']);
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'deal_type']);
            $table->dropIndex(['tenant_id', 'is_priority']);
            $table->dropColumn(['deal_type', 'is_priority']);
        });
    }
};
