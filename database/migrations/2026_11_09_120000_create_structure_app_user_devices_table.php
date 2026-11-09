<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_app_user_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('structure_app_user_id')->constrained('structure_app_users')->cascadeOnDelete();
            $table->string('platform', 20)->default('android');
            $table->string('push_token', 512);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['structure_app_user_id', 'push_token'], 'app_user_device_token_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_app_user_devices');
    }
};
