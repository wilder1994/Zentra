<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guard_shifts', function (Blueprint $table): void {
            $table->foreignId('shift_template_id')
                ->nullable()
                ->after('location_id')
                ->constrained('supervisor_shift_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guard_shifts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_template_id');
        });
    }
};
