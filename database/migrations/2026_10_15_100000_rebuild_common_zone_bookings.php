<?php

declare(strict_types=1);

use App\Models\UserModuleGrant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Reservas de zonas comunes por instalación, con aprobación del admin y evento con invitados. */
return new class extends Migration
{
    private const PERMISSION = 'client.bookings.manage';

    private const ROLES = ['super-admin', 'company-admin', 'admin-accesos'];

    public function up(): void
    {
        Schema::table('common_zones', function (Blueprint $table) {
            $table->foreignId('installation_id')->nullable()->after('client_id')->constrained()->cascadeOnDelete();
            $table->string('booking_mode', 20)->default('exclusive')->after('type');
            $table->boolean('allow_full_day')->default(true)->after('close_time');
            $table->json('weekdays')->nullable()->after('allow_full_day');
            $table->unsignedSmallInteger('min_minutes')->default(60)->after('weekdays');
            $table->unsignedSmallInteger('max_minutes')->nullable()->after('min_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0)->after('max_minutes');
            $table->unsignedSmallInteger('max_advance_days')->default(90)->after('buffer_minutes');
            $table->unsignedSmallInteger('max_per_unit_month')->nullable()->after('max_advance_days');
            $table->text('rules')->nullable()->after('description');
        });

        Schema::table('common_zone_bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('installation_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->foreignId('structure_id')->nullable()->after('housing_unit_id')->constrained()->nullOnDelete();
            $table->foreignId('structure_member_id')->nullable()->after('structure_id')->constrained()->nullOnDelete();
            $table->date('date_until')->nullable()->after('date');
            $table->boolean('full_day')->default(false)->after('date_until');
            $table->json('guests')->nullable()->after('people_count');
            $table->string('origin', 20)->default('admin')->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('origin')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_note', 500)->nullable()->after('reviewed_at');
            $table->foreignId('authorization_id')->nullable()->after('review_note')
                ->constrained('visitor_pre_authorizations')->nullOnDelete();
            $table->index(['client_id', 'status', 'date']);
        });

        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);
        Role::query()->where('guard_name', 'web')->whereIn('name', self::ROLES)->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        UserModuleGrant::query()->where('module', 'authorizations')->whereIn('scope', ['client', 'installation'])->get()
            ->each(function (UserModuleGrant $grant) use ($permission): void {
                UserModuleGrant::query()->firstOrCreate(
                    ['user_id' => $grant->user_id, 'scope' => $grant->scope->value, 'scope_id' => $grant->scope_id, 'module' => 'bookings'],
                    ['level' => $grant->level->value],
                );
                $grant->user?->givePermissionTo($permission);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        UserModuleGrant::query()->where('module', 'bookings')->delete();
        Permission::query()->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('common_zone_bookings', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'status', 'date']);
            $table->dropConstrainedForeignId('authorization_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('structure_member_id');
            $table->dropConstrainedForeignId('structure_id');
            $table->dropConstrainedForeignId('installation_id');
            $table->dropColumn(['date_until', 'full_day', 'guests', 'origin', 'reviewed_at', 'review_note']);
        });

        Schema::table('common_zones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installation_id');
            $table->dropColumn(['booking_mode', 'allow_full_day', 'weekdays', 'min_minutes', 'max_minutes', 'buffer_minutes', 'max_advance_days', 'max_per_unit_month', 'rules']);
        });
    }
};
