<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_posts', function (Blueprint $table) {
            $table->unsignedTinyInteger('service_days')->default(127)->after('modality');
            $table->boolean('serves_holidays')->default(true)->after('service_days');
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_posts', function (Blueprint $table) {
            $table->dropColumn(['service_days', 'serves_holidays']);
        });
    }
};
