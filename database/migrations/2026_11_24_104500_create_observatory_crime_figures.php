<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observatory_crime_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('security_company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->string('color', 7);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['security_company_id', 'slug']);
        });

        Schema::create('observatory_crime_figures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('security_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('observatory_crime_type_id')->constrained('observatory_crime_types')->cascadeOnDelete();
            $table->string('comuna_code', 8);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('total')->default(0);
            $table->timestamps();

            $table->unique(['observatory_crime_type_id', 'comuna_code', 'year'], 'observatory_crime_figures_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observatory_crime_figures');
        Schema::dropIfExists('observatory_crime_types');
    }
};
