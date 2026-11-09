<?php

declare(strict_types=1);

use App\Services\Company\EnsureInstallationProfilesService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_company_id')->constrained('security_companies')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->boolean('requires_dane')->default(false);
            $table->boolean('observatory_community')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['security_company_id', 'code']);
            $table->index(['security_company_id', 'is_active', 'sort_order'], 'inst_profiles_company_active_sort_idx');
        });

        Schema::table('installations', function (Blueprint $table) {
            $table->foreignId('installation_profile_id')
                ->nullable()
                ->after('code')
                ->constrained('installation_profiles')
                ->nullOnDelete();
        });

        app(EnsureInstallationProfilesService::class)->forAllCompanies();

        $companies = DB::table('security_companies')->pluck('id');
        foreach ($companies as $companyId) {
            $profiles = DB::table('installation_profiles')
                ->where('security_company_id', $companyId)
                ->pluck('id', 'code');

            $installations = DB::table('installations')
                ->join('clients', 'clients.id', '=', 'installations.client_id')
                ->where('clients.security_company_id', $companyId)
                ->select('installations.id', 'installations.kind')
                ->get();

            foreach ($installations as $row) {
                $code = filled($row->kind) ? (string) $row->kind : 'otro';
                $profileId = $profiles[$code] ?? $profiles['otro'] ?? null;
                if ($profileId === null) {
                    continue;
                }

                DB::table('installations')
                    ->where('id', $row->id)
                    ->update(['installation_profile_id' => $profileId]);
            }
        }

        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->string('kind', 20)->nullable()->after('code');
        });

        $profiles = DB::table('installation_profiles')->pluck('code', 'id');
        foreach (DB::table('installations')->select('id', 'installation_profile_id')->get() as $row) {
            $code = $row->installation_profile_id !== null
                ? ($profiles[$row->installation_profile_id] ?? null)
                : null;
            DB::table('installations')
                ->where('id', $row->id)
                ->update(['kind' => $code]);
        }

        Schema::table('installations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installation_profile_id');
        });

        Schema::dropIfExists('installation_profiles');
    }
};
