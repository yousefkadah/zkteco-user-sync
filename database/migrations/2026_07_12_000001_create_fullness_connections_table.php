<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the single local "connection" to a Fullness CRM account used by the
 * Connectors feature: the base URL, the (encrypted) Sanctum token issued at
 * login, and the currently selected tenant. The owner's password is never
 * stored — only the revocable token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fullness_connections', function (Blueprint $table) {
            $table->id();
            $table->string('base_url');
            $table->text('token'); // encrypted at rest via the model cast
            $table->json('tenants')->nullable(); // the staff businesses this account can manage
            $table->string('tenant_id')->nullable();
            $table->string('tenant_name')->nullable();
            $table->string('owner_email')->nullable(); // display only
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fullness_connections');
    }
};
