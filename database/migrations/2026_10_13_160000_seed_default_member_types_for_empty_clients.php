<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const NAMES = ['Propietario', 'Inquilino', 'Familiar', 'Empleado doméstico', 'Administración'];

    public function up(): void
    {
        $clientIds = DB::table('clients')
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))->from('member_types')->whereColumn('member_types.client_id', 'clients.id');
            })
            ->pluck('id');

        foreach ($clientIds as $clientId) {
            foreach (self::NAMES as $i => $name) {
                DB::table('member_types')->insert([
                    'client_id' => $clientId,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'is_active' => true,
                    'sort_order' => ($i + 1) * 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Datos de catálogo: no se revierten para no romper personas ya asignadas.
    }
};
