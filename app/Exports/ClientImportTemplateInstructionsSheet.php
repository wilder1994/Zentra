<?php

declare(strict_types=1);

namespace App\Exports;

use App\Support\Client\ClientExcelSchema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

final class ClientImportTemplateInstructionsSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return ClientExcelSchema::INSTRUCTIONS_SHEET;
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return [
            ['Instrucciones — carga de clientes Zentra'],
            [''],
            ['Usa la hoja Clientes. Esta hoja no se importa. La ficha no consume cupo; Accesos y Supervisión sí.'],
            ['Rojo = obligatorio. Gris = opcional.'],
            [''],
            ['Columna', 'Regla'],
            ['Tipo de cliente', 'Persona jurídica / jurídica / PJ, o Persona natural / natural / PN.'],
            ['Nombre comercial', 'Obligatorio.'],
            ['Razón social', 'Obligatoria si es jurídica. Si va vacía, se copia el nombre comercial.'],
            ['Tipo documento', 'Catálogo plataforma (NIT, CC… o el nombre).'],
            ['Número documento', 'Único por empresa. No duplicar en el archivo.'],
            ['Email', 'Obligatorio.'],
            ['Representante / Email representante', 'Obligatorios si es persona jurídica.'],
            ['Tipo de estructura', 'Debe existir en el catálogo (código o nombre, ej. ph / Propiedad horizontal). No se crea desde el Excel.'],
            ['Accesos / Supervisión', 'SI o NO. Vacío = NO. Si no hay cupo, esa fila queda en error.'],
            [''],
            ['Cómo cargar'],
            ['1. Llena la hoja Clientes (o pega la tabla en Carga masiva).'],
            ['2. Revisa el preview: válidas, errores y avisos.'],
            ['3. Aceptar solo si no hay errores.'],
        ];
    }
}
