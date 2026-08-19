<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('buyers_notified_at')->nullable()->after('stage_changed_at');
            $table->unsignedInteger('buyers_notified_count')->nullable()->after('buyers_notified_at');
            $table->string('buyer_notification_status', 20)->nullable()->after('buyers_notified_count');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['buyers_notified_at', 'buyers_notified_count', 'buyer_notification_status']);
        });
    }
};
