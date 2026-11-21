<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_webhook_messages', function (Blueprint $table): void {
            $table->string('sender_name', 120)->nullable()->after('sender');
            $table->unsignedBigInteger('conversation_id')->nullable()->after('payload')->index();
            $table->timestamp('processed_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('meta_webhook_messages', function (Blueprint $table): void {
            $table->dropIndex(['conversation_id']);
            $table->dropColumn(['sender_name', 'conversation_id', 'processed_at']);
        });
    }
};
