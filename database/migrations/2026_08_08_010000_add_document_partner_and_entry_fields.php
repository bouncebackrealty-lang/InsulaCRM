<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->string('address')->nullable()->after('email');
            $table->string('city')->nullable()->after('address');
            $table->string('state', 50)->nullable()->after('city');
            $table->string('zip_code', 20)->nullable()->after('state');
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->json('input_fields')->nullable()->after('merge_fields');
        });

        Schema::create('title_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('closing_attorney')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 50)->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('title_company_id')->nullable()->after('lead_id')->constrained('title_companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('title_company_id');
        });
        Schema::dropIfExists('title_companies');
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('input_fields');
        });
        Schema::table('buyers', function (Blueprint $table) {
            $table->dropColumn(['address', 'city', 'state', 'zip_code']);
        });
    }
};
