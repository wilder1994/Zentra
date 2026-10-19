<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('supervisor_zone_id')->nullable()->after('security_company_id')
                ->constrained('supervisor_zones')->nullOnDelete();
        });

        Schema::create('supervisor_zone_shared_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_zone_id')->constrained('supervisor_zones')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supervisor_zone_id', 'client_id'], 'sup_zone_shared_client_uidx');
        });

        Schema::create('supervisor_shift_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_shift_id')->constrained('supervisor_shifts')->cascadeOnDelete();
            $table->foreignId('supervisor_zone_id')->constrained('supervisor_zones')->cascadeOnDelete();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('added_at');
            $table->foreignId('removed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['supervisor_shift_id', 'removed_at'], 'sup_shift_zone_active_idx');
        });

        foreach (['supervisor_shift_reviews', 'supervisor_field_logs', 'supervisor_recommendations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('supervisor_zone_id')->nullable()->after('id')
                    ->constrained('supervisor_zones')->nullOnDelete();
            });
        }

        DB::statement('UPDATE supervisor_shift_reviews r JOIN supervisor_shifts s ON s.id = r.supervisor_shift_id SET r.supervisor_zone_id = s.supervisor_zone_id');
        DB::statement('UPDATE supervisor_field_logs l JOIN supervisor_shifts s ON s.id = l.supervisor_shift_id SET l.supervisor_zone_id = s.supervisor_zone_id');
        DB::statement('UPDATE supervisor_recommendations r JOIN supervisor_shifts s ON s.id = r.opened_shift_id SET r.supervisor_zone_id = s.supervisor_zone_id');

        Schema::table('supervisor_zones', function (Blueprint $table) {
            $table->dropColumn('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('corporate_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('corporate_email');
        });

        Schema::table('supervisor_zones', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
        });

        foreach (['supervisor_shift_reviews', 'supervisor_field_logs', 'supervisor_recommendations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('supervisor_zone_id');
            });
        }

        Schema::dropIfExists('supervisor_shift_zones');
        Schema::dropIfExists('supervisor_zone_shared_clients');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_zone_id');
        });
    }
};
