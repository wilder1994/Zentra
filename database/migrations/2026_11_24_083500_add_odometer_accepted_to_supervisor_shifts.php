<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_shifts', function (Blueprint $table): void {
            $table->timestamp('odometer_accepted_at')->nullable()->after('km_traveled');
            $table->foreignId('odometer_accepted_by')->nullable()->after('odometer_accepted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_shifts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('odometer_accepted_by');
            $table->dropColumn('odometer_accepted_at');
        });
    }
};
