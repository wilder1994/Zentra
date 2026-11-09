<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('structure_types', 'is_unit')) {
            return;
        }

        Schema::table('structure_types', function (Blueprint $table) {
            $table->dropColumn('is_unit');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('structure_types', 'is_unit')) {
            return;
        }

        Schema::table('structure_types', function (Blueprint $table) {
            $table->boolean('is_unit')->default(false)->after('description');
        });
    }
};
