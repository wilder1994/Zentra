<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_companies', function (Blueprint $table) {
            $table->decimal('scheduled_amount', 14, 2)->nullable()->after('scheduled_change_payment_id');
            $table->string('scheduled_amount_product', 20)->nullable()->after('scheduled_amount');
            $table->string('scheduled_amount_note', 255)->nullable()->after('scheduled_amount_product');
        });
    }

    public function down(): void
    {
        Schema::table('security_companies', function (Blueprint $table) {
            $table->dropColumn(['scheduled_amount', 'scheduled_amount_product', 'scheduled_amount_note']);
        });
    }
};
