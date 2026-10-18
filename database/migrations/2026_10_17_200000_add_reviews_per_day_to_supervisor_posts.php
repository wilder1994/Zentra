<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_posts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('reviews_per_day')->default(1)->after('serves_holidays');
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_posts', function (Blueprint $table): void {
            $table->dropColumn('reviews_per_day');
        });
    }
};
