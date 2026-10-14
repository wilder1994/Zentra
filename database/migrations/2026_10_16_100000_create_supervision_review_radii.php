<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervision_review_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('default_radius_m')->default(80);
            $table->unsignedSmallInteger('max_radius_m')->default(300);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('supervision_review_radii', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('installation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('radius_m');
            $table->string('reason', 255);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['security_company_id', 'client_id', 'installation_id'], 'review_radii_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_review_radii');
        Schema::dropIfExists('supervision_review_settings');
    }
};
