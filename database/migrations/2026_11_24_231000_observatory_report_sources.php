<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('observatory_reports')->where('source', 'comunidad')->update(['source' => 'link']);
        DB::table('observatory_reports')->whereIn('source', ['panel', 'api'])->update(['source' => 'link']);
        DB::table('observatory_reports')->where('source', 'porteria')->update(['source' => 'campo']);
        DB::statement("ALTER TABLE observatory_reports MODIFY source VARCHAR(20) NOT NULL DEFAULT 'link'");
    }

    public function down(): void
    {
        DB::table('observatory_reports')->where('source', 'link')->update(['source' => 'comunidad']);
        DB::statement("ALTER TABLE observatory_reports MODIFY source VARCHAR(20) NOT NULL DEFAULT 'comunidad'");
    }
};
