<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_conversations', function (Blueprint $table): void {
            $table->boolean('is_adult')->nullable()->after('is_anonymous');
        });
    }

    public function down(): void
    {
        Schema::table('meta_conversations', function (Blueprint $table): void {
            $table->dropColumn('is_adult');
        });
    }
};
