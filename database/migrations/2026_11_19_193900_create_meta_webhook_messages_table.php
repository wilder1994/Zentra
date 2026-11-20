<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_webhook_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id', 255)->unique();
            $table->string('phone_number_id', 32);
            $table->string('sender', 32)->nullable();
            $table->string('type', 32)->nullable();
            $table->text('body')->nullable();
            $table->json('payload');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_webhook_messages');
    }
};
