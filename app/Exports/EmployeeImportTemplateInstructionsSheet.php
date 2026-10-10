<?php

declare(strict_types=1);

namespace App\Exports;

use App\Support\Employee\EmployeeExcelSchema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

final class EmployeeImportTemplateInstructionsSheet implements FromArray, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return EmployeeExcelSchema::INSTRUCTIONS_SHEET;
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return [
            ['Instrucciones — carga de empleados Zentra'],
            [''],
            ['Usa la hoja Empleados. Esta hoja no se importa.'],
            ['Rojo = obligatorio en archivo. Gris = opcional en archivo (el correo sí es obligatorio en Zentra).'],
            [''],
            ['Columna', 'Regla'],
            ['Tipo Documento de Identidad', 'Catálogo de la plataforma (CC, CE, PA… o el nombre: Cédula de ciudadanía).'],
            ['Nro. Documento de Identidad', 'Único por empresa. No duplicar en el archivo ni contra empleados ya cargados.'],
            ['Ap. Paterno / Materno', 'Al menos uno de los dos. Si puedes, llena ambos.'],
            ['Nombres', 'Obligatorio.'],
            ['Sexo', 'Hombre o Mujer.'],
            ['Edad', 'No se carga. Zentra la calcula con la fecha de nacimiento.'],
            ['Tipo Colaborador', 'Obligatorio. Si no existe en Tipos de la empresa, se crea al aceptar.'],
            ['Razón Social / Instalaciones / Sector / Puesto', 'No se usan. Déjalas vacías. El árbol (instalación, acceso, puesto) se crea a mano en la ficha del cliente.'],
            ['Cargo', 'Obligatorio. Si no existe en Cargos de la empresa, se crea al aceptar.'],
            ['Fecha Nacimiento', 'Obligatoria. Formato dd-mm-aaaa o fecha Excel.'],
            ['Nacionalidad', 'Obligatoria. Ej. COLOMBIANA.'],
            ['Email Ficha', 'Obligatorio en Zentra aunque el encabezado sea gris. Único por empresa.'],
            ['G.Sanguíneo', 'O+, O-, A+, A-, B+, B-, AB+, AB- o Pendiente (si aún no se conoce). Se ignora el texto entre paréntesis.'],
            ['Mismo CC origen? / Discapacidad', 'SI o NO. Vacío = sin dato / No.'],
            ['Teléfono / residencia / dirección', 'Opcionales. Si no vienen, la ficha queda sin esos datos.'],
            ['Escolaridad / estado civil / hijos', 'Opcionales.'],
            ['Vinculación / cotizante / contrato / ingreso / vencimiento / retiro', 'Opcionales. No archivan al empleado.'],
            ['EPS / pensión / caja / ARL', 'Opcionales (código y nombre).'],
            [''],
            ['No va en este archivo'],
            ['Nombre de fantasía (es del cliente). Archivar / cese. Usuario de acceso (se da después en la ficha).'],
            ['Esta hoja no crea clientes, instalaciones ni puestos.'],
            [''],
            ['Cómo cargar'],
            ['1. Llena la hoja Empleados (o pega la tabla en Carga masiva).'],
            ['2. Revisa el preview: válidas, errores y avisos.'],
            ['3. Aceptar solo está habilitado si no hay errores. Ahí sí se guardan.'],
        ];
    }
}
