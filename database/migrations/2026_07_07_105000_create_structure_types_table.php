<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_company_id')->constrained('security_companies')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['security_company_id', 'code']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('structure_type_id')
                ->nullable()
                ->after('representative_email')
                ->constrained('structure_types')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('structure_type_id');
        });

        Schema::dropIfExists('structure_types');
    }
};
