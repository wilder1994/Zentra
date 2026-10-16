<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->timestamp('access_line_archived_at')->nullable()->after('has_observatory');
            $table->timestamp('supervision_line_archived_at')->nullable()->after('access_line_archived_at');
            $table->timestamp('observatory_line_archived_at')->nullable()->after('supervision_line_archived_at');
        });

        Schema::table('observatory_events', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('closed_by_user_id');
            $table->boolean('archived_by_service_line')->default(false)->after('archived_at');
            $table->index(['client_id', 'archived_at']);
        });

        Schema::table('supervisor_posts', function (Blueprint $table): void {
            $table->boolean('archived_by_service_line')->default(false)->after('archived_with_parent');
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('is_active');
            $table->boolean('archived_by_service_line')->default(false)->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn(['archived_at', 'archived_by_service_line']);
        });

        Schema::table('supervisor_posts', function (Blueprint $table): void {
            $table->dropColumn('archived_by_service_line');
        });

        Schema::table('observatory_events', function (Blueprint $table): void {
            $table->dropIndex(['client_id', 'archived_at']);
            $table->dropColumn(['archived_at', 'archived_by_service_line']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn([
                'access_line_archived_at',
                'supervision_line_archived_at',
                'observatory_line_archived_at',
            ]);
        });
    }
};
