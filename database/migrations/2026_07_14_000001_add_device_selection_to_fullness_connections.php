<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Fullness tenant can have several attendance devices, so the connector lets
 * the operator pick which device's assigned users to sync. Store the tenant's
 * device list plus the chosen device on the connection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fullness_connections', function (Blueprint $table) {
            $table->json('devices')->nullable()->after('tenant_name'); // the tenant's attendance devices
            $table->string('fullness_device_id')->nullable()->after('devices'); // chosen device
            $table->string('fullness_device_name')->nullable()->after('fullness_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('fullness_connections', function (Blueprint $table) {
            $table->dropColumn(['devices', 'fullness_device_id', 'fullness_device_name']);
        });
    }
};
