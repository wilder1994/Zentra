<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexación pasa a cobrarse por empleado: la cantidad contratada de cada empresa con Indexación
 * se convierte en sus empleados activos de hoy. El precio vigente (`indexing_price_*`) no se toca,
 * así que no hay cobro nuevo hasta el próximo recálculo.
 */
return new class extends Migration
{
    private const UNIT_PER_EMPLOYEE = 1000;

    public function up(): void
    {
        $companies = DB::table('security_companies')
            ->where(function ($q) {
                $q->where('indexing_seats', '>', 0)->orWhereNotNull('scheduled_indexing_seats');
            })
            ->get(['id', 'indexing_seats', 'scheduled_indexing_seats']);

        foreach ($companies as $company) {
            $employees = max(1, DB::table('employees')
                ->where('security_company_id', $company->id)
                ->where('is_active', true)
                ->count());

            $update = [];
            if ((int) $company->indexing_seats > 0) {
                $update['indexing_seats'] = $employees;
            }
            if ($company->scheduled_indexing_seats !== null) {
                $update['scheduled_indexing_seats'] = (int) $company->scheduled_indexing_seats > 0 ? $employees : 0;
            }
            if ($update !== []) {
                DB::table('security_companies')->where('id', $company->id)->update($update);
            }
        }

        foreach (DB::table('pricing_settings')->whereNotNull('catalog')->get(['id', 'catalog']) as $row) {
            $catalog = json_decode((string) $row->catalog, true);
            if (! is_array($catalog) || ! isset($catalog['units']['indexing'])) {
                continue;
            }
            $catalog['units']['indexing'] = self::UNIT_PER_EMPLOYEE;
            DB::table('pricing_settings')->where('id', $row->id)->update(['catalog' => json_encode($catalog)]);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: la cantidad anterior en instalaciones no se conserva.
    }
};
