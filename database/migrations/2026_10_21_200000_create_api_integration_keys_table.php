<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->boolean('is_integration')->default(false)->after('abilities');
        });

        Schema::create('api_integration_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('security_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index();
            $table->string('name', 120);
            $table->json('abilities');
            $table->string('token_hint', 12)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('regenerated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_integration_keys');

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn('is_integration');
        });
    }
};
