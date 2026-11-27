<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_companies', function (Blueprint $table): void {
            $table->json('observatory_chart_colors')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('security_companies', function (Blueprint $table): void {
            $table->dropColumn('observatory_chart_colors');
        });
    }
};
