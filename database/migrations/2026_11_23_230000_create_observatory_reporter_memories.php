<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observatory_reporter_memories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 32);
            $table->string('role', 20);
            $table->boolean('is_adult')->nullable();
            $table->boolean('remember')->default(true);
            $table->timestamp('authorization_accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'phone']);
        });

        Schema::table('meta_conversations', function (Blueprint $table): void {
            $table->boolean('remember_preference')->nullable()->after('is_adult');
        });
    }

    public function down(): void
    {
        Schema::table('meta_conversations', function (Blueprint $table): void {
            $table->dropColumn('remember_preference');
        });

        Schema::dropIfExists('observatory_reporter_memories');
    }
};
