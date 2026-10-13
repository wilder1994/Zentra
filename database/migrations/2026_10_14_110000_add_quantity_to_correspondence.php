<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondence', function (Blueprint $table) {
            $table->unsignedSmallInteger('quantity')->default(1)->after('package_type');
        });
    }

    public function down(): void
    {
        Schema::table('correspondence', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
