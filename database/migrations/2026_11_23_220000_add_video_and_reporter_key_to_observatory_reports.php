<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observatory_reports', function (Blueprint $table): void {
            $table->string('video_path')->nullable()->after('photo_paths');
            $table->char('reporter_key', 64)->nullable()->after('ip_hash');
            $table->index(['installation_id', 'reporter_key', 'created_at'], 'obs_reports_person_idx');
        });
    }

    public function down(): void
    {
        Schema::table('observatory_reports', function (Blueprint $table): void {
            $table->dropIndex('obs_reports_person_idx');
            $table->dropColumn(['video_path', 'reporter_key']);
        });
    }
};
