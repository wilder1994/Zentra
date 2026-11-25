<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_model_parameters', function (Blueprint $table): void {
            $table->string('color_low', 7)->default('#14532d')->after('threshold_critical');
            $table->string('color_medium', 7)->default('#854d0e')->after('color_low');
            $table->string('color_high', 7)->default('#9a3412')->after('color_medium');
            $table->string('color_critical', 7)->default('#9f1239')->after('color_high');
        });
    }

    public function down(): void
    {
        Schema::table('risk_model_parameters', function (Blueprint $table): void {
            $table->dropColumn(['color_low', 'color_medium', 'color_high', 'color_critical']);
        });
    }
};
