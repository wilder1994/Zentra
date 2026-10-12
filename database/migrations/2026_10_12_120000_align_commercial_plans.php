<?php

declare(strict_types=1);

use App\Enums\CommercialMetal;
use App\Enums\PackageModality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planes Bronce/Plata/Oro/Platino por cantidad libre de instalaciones:
 * precio pactado, recálculo diferido, historial de precios, contratos por plan y fin de Supervisión ilimitada.
 * Las empresas actuales conservan su precio hasta que el súper admin aplique el catálogo nuevo.
 */
return new class extends Migration
{
    /** Contrato legado que sirve de base para cada plan. */
    private const CONTRACT_SOURCE = [
        'bronce' => 5,
        'plata' => 10,
        'oro' => 50,
        'platino' => 100,
    ];

    public function up(): void
    {
        Schema::table('security_companies', function (Blueprint $table) {
            $table->boolean('price_locked')->default(false)->after('annual_discount_pct');
            $table->date('price_locked_until')->nullable()->after('price_locked');
            $table->string('price_lock_note', 255)->nullable()->after('price_locked_until');
            $table->timestamp('pending_reprice_at')->nullable()->after('price_lock_note');
            $table->string('pending_reprice_reason', 40)->nullable()->after('pending_reprice_at');
            $table->string('pending_reprice_note', 255)->nullable()->after('pending_reprice_reason');
        });

        Schema::create('company_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_company_id')->constrained('security_companies')->cascadeOnDelete();
            $table->string('reason', 40);
            $table->string('billing_cycle', 20)->nullable();
            $table->decimal('previous_amount', 14, 2)->default(0);
            $table->decimal('new_amount', 14, 2)->default(0);
            $table->string('previous_plan', 120)->nullable();
            $table->string('new_plan', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('effective_at');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['security_company_id', 'effective_at']);
        });

        $this->dropUnlimitedSupervision();
        $this->rekeyContracts();
    }

    public function down(): void
    {
        Schema::dropIfExists('company_price_changes');

        Schema::table('security_companies', function (Blueprint $table) {
            $table->dropColumn([
                'price_locked',
                'price_locked_until',
                'price_lock_note',
                'pending_reprice_at',
                'pending_reprice_reason',
                'pending_reprice_note',
            ]);
        });
    }

    private function dropUnlimitedSupervision(): void
    {
        DB::table('security_companies')
            ->where(function ($q): void {
                $q->where('supervision_package_sku', 'sup_unlimited')->orWhere('supervision_unlimited', true);
            })
            ->update([
                'supervision_package_sku' => 'sup_100',
                'supervision_package_size' => 100,
                'max_supervision_clients' => 100,
                'supervision_unlimited' => false,
            ]);

        DB::table('security_companies')
            ->where('scheduled_supervision_package_sku', 'sup_unlimited')
            ->update(['scheduled_supervision_package_sku' => 'sup_100']);

        DB::table('commercial_signup_intents')
            ->where('supervision_package_sku', 'sup_unlimited')
            ->update(['supervision_package_sku' => 'sup_100']);
    }

    private function rekeyContracts(): void
    {
        $now = now();

        foreach (CommercialMetal::cases() as $metal) {
            foreach ([PackageModality::Manual, PackageModality::Hardware] as $modality) {
                $key = $metal->value.'_'.$modality->value;
                $exists = DB::table('legal_corpus_versions')
                    ->where('type', 'contract')
                    ->where('package_sku', $key)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $legacy = 'pack_'.self::CONTRACT_SOURCE[$metal->value].'_'.$modality->value;
                $source = DB::table('legal_corpus_versions')
                    ->where('type', 'contract')
                    ->where('package_sku', $legacy)
                    ->orderByRaw('superseded_at IS NULL DESC')
                    ->orderByDesc('id')
                    ->first();
                if ($source === null) {
                    continue;
                }

                $title = 'Contrato de licencia · Plan '.$metal->label().' · '.$modality->label();
                DB::table('legal_corpus_versions')->insert([
                    'type' => 'contract',
                    'package_sku' => $key,
                    'version' => '1.0',
                    'title' => $title,
                    'content' => $source->content,
                    'effective_from' => $now->toDateString(),
                    'superseded_at' => null,
                    'content_hash' => hash('sha256', (string) $source->content),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('legal_corpus_versions')
            ->where('type', 'contract')
            ->where('package_sku', 'like', 'pack\_%')
            ->whereNull('superseded_at')
            ->update(['superseded_at' => $now]);
    }
};
