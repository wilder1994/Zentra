<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_id', 40)->index();
            // Igual a wa_id mientras la conversación está abierta; null al cerrar. Evita dos abiertas por número.
            $table->string('open_key', 40)->nullable()->unique();
            $table->string('profile_name', 120)->nullable();
            $table->string('state', 20);
            $table->unsignedBigInteger('installation_id')->nullable();
            $table->unsignedBigInteger('report_type_id')->nullable();
            $table->text('body')->nullable();
            $table->json('media')->nullable();
            $table->json('candidates')->nullable();
            $table->string('reporter_role', 20)->nullable();
            $table->boolean('is_anonymous')->nullable();
            $table->timestamp('last_message_at');
            $table->unsignedBigInteger('report_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_conversations');
    }
};
