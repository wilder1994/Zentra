<?php

declare(strict_types=1);

use App\Enums\CommercialMetal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cupos por plan (empleados, puestos por instalación, empleados por puesto),
 * Indexación y Observatorio con cantidad propia, archivo unificado y solicitudes de ampliación.
 * Lo que hoy excede los nuevos límites queda como cupo adicional: ninguna empresa se bloquea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_companies', function (Blueprint $table): void {
            $table->unsignedInteger('indexing_seats')->default(0)->after('max_supervision_clients');
            $table->unsignedInteger('observatory_seats')->default(0)->after('indexing_seats');
            $table->unsignedInteger('extra_employees')->default(0)->after('observatory_seats');
            $table->unsignedSmallInteger('posts_per_installation')->nullable()->after('extra_employees');
            $table->unsignedSmallInteger('employees_per_post')->nullable()->after('posts_per_installation');
            $table->string('quota_note', 500)->nullable()->after('employees_per_post');
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('has_observatory')->default(false)->after('has_supervision');
            $table->boolean('archived_with_parent')->default(false)->after('archived_at');
        });

        Schema::table('installations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_posts')->nullable()->after('is_active');
            $table->string('max_posts_note', 255)->nullable()->after('max_posts');
            $table->timestamp('archived_at')->nullable()->after('max_posts_note');
            $table->boolean('archived_with_parent')->default(false)->after('archived_at');
        });

        Schema::table('supervisor_posts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_employees')->nullable()->after('is_active');
            $table->string('max_employees_note', 255)->nullable()->after('max_employees');
            $table->timestamp('archived_at')->nullable()->after('max_employees_note');
            $table->boolean('archived_with_parent')->default(false)->after('archived_at');
        });

        Schema::create('quota_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('security_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 30);
            $table->foreignId('installation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supervisor_post_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();
            $table->index(['security_company_id', 'status']);
        });

        $now = now();
        DB::table('clients')->where('lifecycle', 'archived_company')->update(['archived_with_parent' => true]);
        DB::table('clients')->where('lifecycle', 'active')->where('is_active', false)->whereNull('archived_at')->update(['archived_at' => $now]);
        DB::table('installations')->where('is_active', false)->update(['archived_at' => $now]);
        DB::table('supervisor_posts')->where('is_active', false)->update(['archived_at' => $now]);

        $this->backfillProducts();
        $this->backfillExcess();

        Schema::table('security_companies', function (Blueprint $table): void {
            $table->dropColumn(['has_indexing', 'has_observatory']);
        });
    }

    public function down(): void
    {
        Schema::table('security_companies', function (Blueprint $table): void {
            $table->boolean('has_indexing')->default(true)->after('max_clients');
            $table->boolean('has_observatory')->default(true)->after('has_indexing');
        });

        DB::table('security_companies')->update([
            'has_indexing' => DB::raw('indexing_seats > 0'),
            'has_observatory' => DB::raw('observatory_seats > 0'),
        ]);

        Schema::dropIfExists('quota_requests');

        Schema::table('supervisor_posts', function (Blueprint $table): void {
            $table->dropColumn(['max_employees', 'max_employees_note', 'archived_at', 'archived_with_parent']);
        });

        Schema::table('installations', function (Blueprint $table): void {
            $table->dropColumn(['max_posts', 'max_posts_note', 'archived_at', 'archived_with_parent']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn(['has_observatory', 'archived_with_parent']);
        });

        Schema::table('security_companies', function (Blueprint $table): void {
            $table->dropColumn([
                'indexing_seats', 'observatory_seats', 'extra_employees',
                'posts_per_installation', 'employees_per_post', 'quota_note',
            ]);
        });
    }

    /** Indexación y Observatorio venían incluidos: pasan a cantidad propia sin cambiar el precio pactado. */
    private function backfillProducts(): void
    {
        $companies = DB::table('security_companies')
            ->get(['id', 'max_clients', 'max_supervision_clients', 'has_indexing', 'has_observatory']);

        foreach ($companies as $company) {
            $access = (int) ($company->max_clients ?? 0);
            $supervision = (int) ($company->max_supervision_clients ?? 0);
            $indexing = $company->has_indexing === null || (bool) $company->has_indexing
                ? max($access, $supervision)
                : 0;

            $observatory = 0;
            if ($access > 0 && ($company->has_observatory === null || (bool) $company->has_observatory)) {
                $clientIds = DB::table('clients')
                    ->where('security_company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->where(function ($q): void {
                        $q->where('has_access', true)
                            ->orWhere('panel_modules->observatory', true);
                    })
                    ->pluck('id');

                DB::table('clients')->whereIn('id', $clientIds)->update(['has_observatory' => true]);

                $used = DB::table('installations')
                    ->whereIn('client_id', $clientIds)
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->count();
                $observatory = max($access, $used);
            }

            DB::table('security_companies')->where('id', $company->id)->update([
                'indexing_seats' => $indexing,
                'observatory_seats' => $observatory,
            ]);
        }
    }

    private function backfillExcess(): void
    {
        $companies = DB::table('security_companies')
            ->get(['id', 'max_clients', 'max_supervision_clients', 'indexing_seats', 'observatory_seats']);

        foreach ($companies as $company) {
            $metal = CommercialMetal::highest(...array_map(
                fn (int $seats) => $seats > 0 ? CommercialMetal::fromSeats($seats) : null,
                [
                    (int) $company->max_clients,
                    (int) $company->max_supervision_clients,
                    (int) $company->indexing_seats,
                    (int) $company->observatory_seats,
                ],
            )) ?? CommercialMetal::Bronce;

            $employees = DB::table('employees')
                ->where('security_company_id', $company->id)
                ->where('is_active', true)
                ->count();
            $extra = max(0, $employees - $metal->employeeCap());
            if ($extra > 0) {
                DB::table('security_companies')->where('id', $company->id)->update([
                    'extra_employees' => $extra,
                    'quota_note' => 'Adicional por empleados existentes al aplicar los nuevos límites.',
                ]);
            }

            $clientIds = DB::table('clients')->where('security_company_id', $company->id)->pluck('id');

            $postsByInstallation = DB::table('supervisor_posts')
                ->whereIn('client_id', $clientIds)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->whereNotNull('installation_id')
                ->selectRaw('installation_id, COUNT(*) as total')
                ->groupBy('installation_id')
                ->havingRaw('COUNT(*) > ?', [$metal->postsPerInstallation()])
                ->get();
            foreach ($postsByInstallation as $row) {
                DB::table('installations')->where('id', $row->installation_id)->update([
                    'max_posts' => (int) $row->total,
                    'max_posts_note' => 'Adicional por puestos existentes al aplicar los nuevos límites.',
                ]);
            }

            $assignments = DB::table('supervisor_post_employee as spe')
                ->join('supervisor_posts as sp', 'sp.id', '=', 'spe.supervisor_post_id')
                ->whereIn('sp.client_id', $clientIds)
                ->whereNull('sp.deleted_at')
                ->selectRaw('spe.supervisor_post_id, COUNT(*) as total')
                ->groupBy('spe.supervisor_post_id')
                ->havingRaw('COUNT(*) > ?', [$metal->employeesPerPost()])
                ->get();
            foreach ($assignments as $row) {
                DB::table('supervisor_posts')->where('id', $row->supervisor_post_id)->update([
                    'max_employees' => (int) $row->total,
                    'max_employees_note' => 'Adicional por asignaciones existentes al aplicar los nuevos límites.',
                ]);
            }
        }
    }
};
