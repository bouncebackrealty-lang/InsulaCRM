<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addBusinessName = ! Schema::hasColumn('contractors', 'business_name');
        $addMailingAddress = ! Schema::hasColumn('contractors', 'mailing_address');
        $addLicenseNumber = ! Schema::hasColumn('contractors', 'license_number');

        if (! $addBusinessName && ! $addMailingAddress && ! $addLicenseNumber) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table) use ($addBusinessName, $addMailingAddress, $addLicenseNumber): void {
            if ($addBusinessName) {
                $table->string('business_name')->nullable()->after('name');
            }

            if ($addMailingAddress) {
                $table->string('mailing_address')->nullable()->after('email');
            }

            if ($addLicenseNumber) {
                $table->string('license_number')->nullable()->after('mailing_address');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['business_name', 'mailing_address', 'license_number'],
            static fn (string $column): bool => Schema::hasColumn('contractors', $column),
        ));

        if ($columns !== []) {
            Schema::table('contractors', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
