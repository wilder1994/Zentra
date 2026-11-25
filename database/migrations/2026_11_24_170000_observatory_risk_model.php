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
        Schema::table('observatory_report_types', function (Blueprint $table): void {
            $table->foreignId('security_company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('impacto')->default(5)->after('level');
            $table->unsignedTinyInteger('urgencia')->default(5)->after('impacto');
        });

        $companies = DB::table('observatory_report_types as types')
            ->join('clients', 'clients.id', '=', 'types.client_id')
            ->select('types.id', 'clients.security_company_id', 'types.level')
            ->get();

        foreach ($companies as $row) {
            [$impacto, $urgencia] = $this->axesFromLevel((int) $row->level);
            DB::table('observatory_report_types')->where('id', $row->id)->update([
                'security_company_id' => $row->security_company_id,
                'impacto' => $impacto,
                'urgencia' => $urgencia,
            ]);
        }

        $keep = [];
        $rows = DB::table('observatory_report_types')->orderBy('id')->get(['id', 'security_company_id', 'slug']);
        foreach ($rows as $row) {
            if ($row->security_company_id === null) {
                DB::table('observatory_reports')->where('observatory_report_type_id', $row->id)->update([
                    'observatory_report_type_id' => null,
                ]);
                DB::table('observatory_report_types')->where('id', $row->id)->delete();

                continue;
            }
            $key = $row->security_company_id.'|'.$row->slug;
            if (! isset($keep[$key])) {
                $keep[$key] = (int) $row->id;

                continue;
            }
            DB::table('observatory_reports')->where('observatory_report_type_id', $row->id)->update([
                'observatory_report_type_id' => $keep[$key],
            ]);
            DB::table('observatory_report_types')->where('id', $row->id)->delete();
        }

        Schema::table('observatory_report_types', function (Blueprint $table): void {
            $table->dropUnique(['client_id', 'slug']);
            $table->dropConstrainedForeignId('client_id');
        });

        DB::statement('ALTER TABLE observatory_report_types MODIFY security_company_id BIGINT UNSIGNED NOT NULL');

        Schema::table('observatory_report_types', function (Blueprint $table): void {
            $table->unique(['security_company_id', 'slug']);
        });

        Schema::table('observatory_crime_types', function (Blueprint $table): void {
            $table->unsignedTinyInteger('impacto')->default(5)->after('color');
            $table->unsignedTinyInteger('probabilidad')->default(5)->after('impacto');
        });

        Schema::create('risk_model_parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('security_company_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('alpha_percent')->default(55);
            $table->unsignedTinyInteger('gamma_percent')->default(15);
            $table->unsignedSmallInteger('half_life_days')->default(23);
            $table->unsignedSmallInteger('report_points_for_full')->default(40);
            $table->unsignedTinyInteger('threshold_low')->default(0);
            $table->unsignedTinyInteger('threshold_medium')->default(30);
            $table->unsignedTinyInteger('threshold_high')->default(60);
            $table->unsignedTinyInteger('threshold_critical')->default(80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_model_parameters');

        Schema::table('observatory_crime_types', function (Blueprint $table): void {
            $table->dropColumn(['impacto', 'probabilidad']);
        });

        Schema::table('observatory_report_types', function (Blueprint $table): void {
            $table->dropUnique(['security_company_id', 'slug']);
            $table->foreignId('client_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropConstrainedForeignId('security_company_id');
            $table->dropColumn(['impacto', 'urgencia']);
        });
    }

    /** @return array{0: int, 1: int} */
    private function axesFromLevel(int $level): array
    {
        return match ($level) {
            4, 3 => [8, 8],
            2 => [6, 6],
            default => [4, 5],
        };
    }
};
