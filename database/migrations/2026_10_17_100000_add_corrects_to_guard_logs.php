<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guard_logs', function (Blueprint $table) {
            $table->foreignId('corrects_guard_log_id')->nullable()->after('supervisor_name')
                ->constrained('guard_logs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guard_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrects_guard_log_id');
        });
    }
};
