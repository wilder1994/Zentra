<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observatory_reporter_memories', function (Blueprint $table): void {
            $table->string('contact_name', 120)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('observatory_reporter_memories', function (Blueprint $table): void {
            $table->dropColumn('contact_name');
        });
    }
};
