<?php

declare(strict_types=1);

use App\Services\Personnel\EnsurePersonnelCatalogService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('security_company_id')->constrained('security_companies')->cascadeOnDelete();
            $table->string('slug', 32);
            $table->string('name', 120);
            $table->string('behavior', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['security_company_id', 'slug']);
        });

        Schema::create('personnel_folder_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('personnel_folder_id')->constrained('personnel_folders')->cascadeOnDelete();
            $table->foreignId('security_company_id')->constrained('security_companies')->cascadeOnDelete();
            $table->string('slug', 64);
            $table->string('name', 160);
            $table->string('requirement', 20)->default('optional');
            $table->boolean('is_repeatable')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['personnel_folder_id', 'slug']);
        });

        $companies = DB::table('security_companies')->pluck('id');
        $ensure = app(EnsurePersonnelCatalogService::class);
        foreach ($companies as $companyId) {
            $ensure->ensure((int) $companyId);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_folder_types');
        Schema::dropIfExists('personnel_folders');
    }
};
