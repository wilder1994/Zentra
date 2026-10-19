<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_uniform_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['security_company_id', 'name']);
        });

        Schema::create('supervisor_training_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('normalized_name', 160);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_from', 10)->default('panel');
            $table->timestamps();

            $table->unique(['security_company_id', 'normalized_name'], 'sup_training_topic_norm_uidx');
        });

        Schema::create('supervisor_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_field_log_id')->constrained('supervisor_field_logs')->cascadeOnDelete();
            $table->foreignId('supervisor_shift_review_id')->constrained('supervisor_shift_reviews')->cascadeOnDelete();
            $table->foreignId('supervisor_shift_id')->constrained('supervisor_shifts')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_training_topic_id')->constrained('supervisor_training_topics')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supervisor_zone_id')->nullable()->constrained('supervisor_zones')->nullOnDelete();
            $table->string('kind', 12);
            $table->string('modality', 12);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->char('period', 7);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['employee_id', 'supervisor_training_topic_id', 'period'], 'sup_training_emp_topic_period_uidx');
            $table->index(['security_company_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_trainings');
        Schema::dropIfExists('supervisor_training_topics');
        Schema::dropIfExists('supervisor_uniform_items');
    }
};
