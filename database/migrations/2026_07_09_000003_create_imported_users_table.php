<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->unsignedInteger('device_uid')->nullable();
            $table->string('user_id');
            $table->string('name');
            $table->string('name_ascii');
            $table->string('password')->nullable();
            $table->string('card_number')->nullable();
            $table->string('privilege')->default('user'); // user | admin
            $table->boolean('is_valid')->default(true);
            $table->json('validation_errors')->nullable();
            $table->string('sync_status')->default('pending'); // pending | synced | failed | skipped
            $table->string('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_users');
    }
};
