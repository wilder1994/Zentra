<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('structure_app_users', function (Blueprint $table): void {
            $table->string('delivery_channel', 20)->default('android')->after('is_active');
            $table->timestamp('credentials_sent_at')->nullable()->after('last_login_at');
        });

        Schema::create('structure_app_user_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('structure_app_user_id')->constrained('structure_app_users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['structure_app_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_app_user_events');

        Schema::table('structure_app_users', function (Blueprint $table): void {
            $table->dropColumn(['delivery_channel', 'credentials_sent_at']);
        });
    }
};
