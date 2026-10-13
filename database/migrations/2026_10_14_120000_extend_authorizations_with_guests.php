<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_pre_authorizations', function (Blueprint $table) {
            $table->foreignId('structure_id')->nullable()->change();
            $table->foreignId('installation_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->string('type', 20)->default('visit')->after('structure_id');
            $table->string('origin', 20)->default('admin')->after('type');
            $table->string('destination_text', 150)->nullable()->after('origin');
            $table->string('authorized_text', 150)->nullable()->after('member_id');
            $table->dateTime('valid_from')->nullable()->after('valid_for_date');
            $table->dateTime('valid_until')->nullable()->after('valid_from');
            $table->boolean('multi_entry')->default(false)->after('valid_until');
            $table->string('vehicle_plate', 15)->nullable()->after('multi_entry');
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable()->after('created_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->index(['client_id', 'valid_until', 'status']);
        });

        Schema::create('authorization_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authorization_id')->constrained('visitor_pre_authorizations')->cascadeOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 10)->default('CC');
            $table->string('document_number', 30)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->unsignedInteger('entries_count')->default(0);
            $table->dateTime('first_entry_at')->nullable();
            $table->dateTime('last_entry_at')->nullable();
            $table->timestamps();

            $table->unique(['authorization_id', 'document_number']);
            $table->index(['client_id', 'document_number']);
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->foreignId('authorization_guest_id')->nullable()->after('authorized_text')
                ->constrained('authorization_guests')->nullOnDelete();
        });

        DB::table('visitor_pre_authorizations')->whereNull('valid_from')->orderBy('id')->get()->each(function (object $row): void {
            DB::table('visitor_pre_authorizations')->where('id', $row->id)->update([
                'installation_id' => DB::table('structures')->where('id', $row->structure_id)->value('installation_id'),
                'valid_from' => $row->valid_for_date.' 00:00:00',
                'valid_until' => $row->valid_for_date.' 23:59:59',
            ]);

            $name = trim((string) $row->visitor_name);
            if ($name === '') {
                return;
            }
            $parts = preg_split('/\s+/', $name, 2) ?: [$name];
            $document = $row->visitor_document ? trim((string) $row->visitor_document) : null;
            $visitorId = $document
                ? DB::table('visitors')->where('client_id', $row->client_id)->where('document_number', $document)->whereNull('deleted_at')->value('id')
                : null;

            DB::table('authorization_guests')->insert([
                'client_id' => $row->client_id,
                'authorization_id' => $row->id,
                'visitor_id' => $visitorId,
                'document_number' => $document ?: null,
                'first_name' => $parts[0],
                'last_name' => $parts[1] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('authorization_guest_id');
        });
        Schema::dropIfExists('authorization_guests');
        Schema::table('visitor_pre_authorizations', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'valid_until', 'status']);
            $table->dropConstrainedForeignId('installation_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['type', 'origin', 'destination_text', 'authorized_text', 'valid_from', 'valid_until', 'multi_entry', 'vehicle_plate', 'cancelled_at']);
        });
    }
};
