<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correspondence', function (Blueprint $table) {
            $table->foreignId('structure_id')->nullable()->after('resident_id')->constrained('structures')->nullOnDelete();
            $table->foreignId('structure_member_id')->nullable()->after('structure_id')->constrained('structure_members')->nullOnDelete();
            $table->string('recipient_text', 150)->nullable()->after('structure_member_id');
            $table->foreignId('delivered_to_member_id')->nullable()->after('delivered_by')->constrained('structure_members')->nullOnDelete();
            $table->string('delivered_to_name', 150)->nullable()->after('delivered_to_member_id');
            $table->string('delivered_to_document', 50)->nullable()->after('delivered_to_name');
            $table->string('delivery_photo_path', 255)->nullable()->after('photo_path');
            $table->dateTime('voided_at')->nullable()->after('delivery_photo_path');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable()->after('voided_by');
            $table->index(['client_id', 'status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('correspondence', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'status', 'received_at']);
            $table->dropConstrainedForeignId('voided_by');
            $table->dropConstrainedForeignId('delivered_to_member_id');
            $table->dropConstrainedForeignId('structure_member_id');
            $table->dropConstrainedForeignId('structure_id');
            $table->dropColumn(['recipient_text', 'delivered_to_name', 'delivered_to_document', 'delivery_photo_path', 'voided_at', 'void_reason']);
        });
    }
};
