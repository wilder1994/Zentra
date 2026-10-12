<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexación y Observatorio se cobran por instalación como Accesos y Supervisión.
 * Las empresas existentes quedan sin valor en estos productos hasta su próximo recálculo de precio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_companies', function (Blueprint $table): void {
            $table->decimal('indexing_price_monthly', 12, 2)->nullable()->after('supervision_package_price_annual');
            $table->decimal('indexing_price_annual', 12, 2)->nullable()->after('indexing_price_monthly');
            $table->decimal('observatory_price_monthly', 12, 2)->nullable()->after('indexing_price_annual');
            $table->decimal('observatory_price_annual', 12, 2)->nullable()->after('observatory_price_monthly');
            $table->unsignedInteger('scheduled_indexing_seats')->nullable()->after('scheduled_supervision_package_sku');
            $table->unsignedInteger('scheduled_observatory_seats')->nullable()->after('scheduled_indexing_seats');
        });

        Schema::table('commercial_signup_intents', function (Blueprint $table): void {
            $table->unsignedInteger('indexing_seats')->default(0)->after('supervision_package_sku');
            $table->unsignedInteger('observatory_seats')->default(0)->after('indexing_seats');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_signup_intents', function (Blueprint $table): void {
            $table->dropColumn(['indexing_seats', 'observatory_seats']);
        });

        Schema::table('security_companies', function (Blueprint $table): void {
            $table->dropColumn([
                'indexing_price_monthly',
                'indexing_price_annual',
                'observatory_price_monthly',
                'observatory_price_annual',
                'scheduled_indexing_seats',
                'scheduled_observatory_seats',
            ]);
        });
    }
};
