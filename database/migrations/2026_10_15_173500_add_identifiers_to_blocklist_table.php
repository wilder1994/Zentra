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
        Schema::table('blocklist', function (Blueprint $table): void {
            $table->string('document_number', 40)->nullable()->after('reason');
            $table->string('plate', 20)->nullable()->after('document_number');
            $table->string('display_name', 160)->nullable()->after('plate');
            $table->index(['client_id', 'document_number']);
            $table->index(['client_id', 'plate']);
        });

        DB::statement('ALTER TABLE blocklist MODIFY blockable_type VARCHAR(50) NULL');
        DB::statement('ALTER TABLE blocklist MODIFY blockable_id BIGINT UNSIGNED NULL');

        $this->backfillIdentifiers();
    }

    public function down(): void
    {
        Schema::table('blocklist', function (Blueprint $table): void {
            $table->dropIndex(['client_id', 'document_number']);
            $table->dropIndex(['client_id', 'plate']);
            $table->dropColumn(['document_number', 'plate', 'display_name']);
        });

        DB::statement('ALTER TABLE blocklist MODIFY blockable_type VARCHAR(50) NOT NULL');
        DB::statement('ALTER TABLE blocklist MODIFY blockable_id BIGINT UNSIGNED NOT NULL');
    }

    private function backfillIdentifiers(): void
    {
        $rows = DB::table('blocklist')->whereNull('document_number')->whereNull('plate')->get();

        foreach ($rows as $row) {
            $type = (string) $row->blockable_type;
            $id = (int) $row->blockable_id;
            if ($id < 1) {
                continue;
            }

            $document = null;
            $plate = null;
            $name = null;

            if (in_array($type, ['visitor', 'App\\Models\\Visitor'], true) || str_contains($type, 'Visitor')) {
                $subject = DB::table('visitors')->where('id', $id)->first();
                $document = $subject?->document_number;
                $name = trim(($subject?->first_name ?? '').' '.($subject?->last_name ?? '')) ?: null;
            } elseif (in_array($type, ['resident', 'App\\Models\\Resident'], true) || str_contains($type, 'Resident')) {
                $subject = DB::table('residents')->where('id', $id)->first();
                $document = $subject?->document_number;
                $name = trim(($subject?->first_name ?? '').' '.($subject?->last_name ?? '')) ?: null;
            } elseif (in_array($type, ['vehicle', 'App\\Models\\Vehicle'], true) || str_contains($type, 'Vehicle')) {
                $subject = DB::table('vehicles')->where('id', $id)->first();
                $plate = $subject?->plate ? strtoupper((string) $subject->plate) : null;
                $name = $plate;
            }

            if ($document === null && $plate === null) {
                continue;
            }

            DB::table('blocklist')->where('id', $row->id)->update([
                'document_number' => $document,
                'plate' => $plate,
                'display_name' => $name,
            ]);
        }
    }
};
