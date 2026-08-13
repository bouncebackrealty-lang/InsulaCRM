<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->foreignId('selected_buyer_id')
                ->nullable()
                ->after('title_company_id')
                ->constrained('buyers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('selected_buyer_id');
        });
    }
};
