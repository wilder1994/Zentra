<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('single_session_device', 64)->nullable()->after('single_session_token');
            $table->string('mobile_device_id', 100)->nullable()->after('single_session_device');
            $table->string('mobile_device_label', 150)->nullable()->after('mobile_device_id');
            $table->timestamp('mobile_device_bound_at')->nullable()->after('mobile_device_label');
        });

        Schema::create('device_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('security_company_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->string('device_label', 150)->nullable();
            $table->string('previous_device_id', 100)->nullable();
            $table->string('previous_device_label', 150)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('pendiente');
            $table->timestamp('expires_at');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['security_company_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_change_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['single_session_device', 'mobile_device_id', 'mobile_device_label', 'mobile_device_bound_at']);
        });
    }
};
