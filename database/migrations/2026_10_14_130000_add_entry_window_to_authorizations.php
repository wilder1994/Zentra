<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_pre_authorizations', function (Blueprint $table) {
            $table->time('entry_time_from')->nullable()->after('valid_until');
            $table->time('entry_time_until')->nullable()->after('entry_time_from');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_pre_authorizations', function (Blueprint $table) {
            $table->dropColumn(['entry_time_from', 'entry_time_until']);
        });
    }
};
