<?php

declare(strict_types=1);

namespace App\Support\Geo;

/** Veredas y sectores rurales de Cali. Acuerdo 0636 de 2026 (DIVIPOLA). */
final class CaliVeredaCatalog
{
    /** @var list<array{code: string, name: string, corregimiento: string}>|null */
    private static ?array $rows = null;

    /** @return list<array{code: string, name: string, corregimiento: string, label: string}> */
    public static function all(): array
    {
        if (self::$rows === null) {
            self::$rows = [
                ['code' => 'v01', 'name' => 'Navarro (Cabecera)', 'corregimiento' => 'Navarro'],
                ['code' => 'v02', 'name' => 'El Estero', 'corregimiento' => 'Navarro'],
                ['code' => 'v03', 'name' => 'Sector Jarillón Río Cauca', 'corregimiento' => 'Navarro'],
                ['code' => 'v04', 'name' => 'El Hormiguero (Cabecera)', 'corregimiento' => 'El Hormiguero'],
                ['code' => 'v05', 'name' => 'Morgan', 'corregimiento' => 'El Hormiguero'],
                ['code' => 'v06', 'name' => 'La Paila', 'corregimiento' => 'El Hormiguero'],
                ['code' => 'v07', 'name' => 'Cauca Viejo', 'corregimiento' => 'El Hormiguero'],
                ['code' => 'v08', 'name' => 'Cascajal', 'corregimiento' => 'El Hormiguero'],
                ['code' => 'v09', 'name' => 'Valle del Lili', 'corregimiento' => 'El Hormiguero'],
                ['code' => 'v10', 'name' => 'Pance (Cabecera)', 'corregimiento' => 'Pance'],
                ['code' => 'v11', 'name' => 'La Vorágine', 'corregimiento' => 'Pance'],
                ['code' => 'v12', 'name' => 'San Francisco', 'corregimiento' => 'Pance'],
                ['code' => 'v13', 'name' => 'El Jardín', 'corregimiento' => 'Pance'],
                ['code' => 'v14', 'name' => 'Pico de Águila', 'corregimiento' => 'Pance'],
                ['code' => 'v15', 'name' => 'El Banqueo', 'corregimiento' => 'Pance'],
                ['code' => 'v16', 'name' => 'El Peón', 'corregimiento' => 'Pance'],
                ['code' => 'v17', 'name' => 'La María', 'corregimiento' => 'Pance'],
                ['code' => 'v18', 'name' => 'La Viga', 'corregimiento' => 'Pance'],
                ['code' => 'v19', 'name' => 'La Buitrera (Cabecera)', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v20', 'name' => 'La Riverita', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v21', 'name' => 'El Rosario', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v22', 'name' => 'El Otoño', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v23', 'name' => 'Alto de los Mangos', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v24', 'name' => 'La Luisa', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v25', 'name' => 'Parque La Bandera', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v26', 'name' => 'Acueducto La Reforma', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v27', 'name' => 'Sector Club Campestre (Rural)', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v28', 'name' => 'La Fonda', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v29', 'name' => 'Los Arrayanes - Los Pinos', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v30', 'name' => 'Bella Suiza', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v31', 'name' => 'La Sirena', 'corregimiento' => 'La Buitrera'],
                ['code' => 'v32', 'name' => 'Villacarmelo (Cabecera)', 'corregimiento' => 'Villacarmelo'],
                ['code' => 'v33', 'name' => 'La Fonda', 'corregimiento' => 'Villacarmelo'],
                ['code' => 'v34', 'name' => 'Dos Quebradas', 'corregimiento' => 'Villacarmelo'],
                ['code' => 'v35', 'name' => 'La Candelaria', 'corregimiento' => 'Villacarmelo'],
                ['code' => 'v36', 'name' => 'El Carmen', 'corregimiento' => 'Villacarmelo'],
                ['code' => 'v37', 'name' => 'Alto de los Mangos', 'corregimiento' => 'Villacarmelo'],
                ['code' => 'v38', 'name' => 'Los Andes (Cabecera)', 'corregimiento' => 'Los Andes'],
                ['code' => 'v39', 'name' => 'Cárpatos', 'corregimiento' => 'Los Andes'],
                ['code' => 'v40', 'name' => 'Quebrada Honda', 'corregimiento' => 'Los Andes'],
                ['code' => 'v41', 'name' => 'Pueblo Nuevo', 'corregimiento' => 'Los Andes'],
                ['code' => 'v42', 'name' => 'El Faro', 'corregimiento' => 'Los Andes'],
                ['code' => 'v43', 'name' => 'Cristo Rey - La Hamaca', 'corregimiento' => 'Los Andes'],
                ['code' => 'v44', 'name' => 'El Mango - La Reforma', 'corregimiento' => 'Los Andes'],
                ['code' => 'v45', 'name' => 'La Carolina - Andes Bajo', 'corregimiento' => 'Los Andes'],
                ['code' => 'v46', 'name' => 'Mameyal', 'corregimiento' => 'Los Andes'],
                ['code' => 'v47', 'name' => 'Pilas del Cabuyal', 'corregimiento' => 'Los Andes'],
                ['code' => 'v48', 'name' => 'Dinastía Ventiaderos', 'corregimiento' => 'Los Andes'],
                ['code' => 'v49', 'name' => 'Ecoparque Cristo Rey', 'corregimiento' => 'Los Andes'],
                ['code' => 'v50', 'name' => 'Brisas de los Cristales', 'corregimiento' => 'Los Andes'],
                ['code' => 'v51', 'name' => 'Atenas', 'corregimiento' => 'Los Andes'],
                ['code' => 'v52', 'name' => 'Mónaco', 'corregimiento' => 'Los Andes'],
                ['code' => 'v53', 'name' => 'Pichindé (Cabecera)', 'corregimiento' => 'Pichindé'],
                ['code' => 'v54', 'name' => 'Peñas Blancas', 'corregimiento' => 'Pichindé'],
                ['code' => 'v55', 'name' => 'La Cajita', 'corregimiento' => 'Pichindé'],
                ['code' => 'v56', 'name' => 'La Leonera (Cabecera)', 'corregimiento' => 'La Leonera'],
                ['code' => 'v57', 'name' => 'El Pato', 'corregimiento' => 'La Leonera'],
                ['code' => 'v58', 'name' => 'El Porvenir', 'corregimiento' => 'La Leonera'],
                ['code' => 'v59', 'name' => 'El Pajuil', 'corregimiento' => 'La Leonera'],
                ['code' => 'v60', 'name' => 'La Cajita', 'corregimiento' => 'La Leonera'],
                ['code' => 'v61', 'name' => 'Felidia (Cabecera)', 'corregimiento' => 'Felidia'],
                ['code' => 'v62', 'name' => 'La Esperanza', 'corregimiento' => 'Felidia'],
                ['code' => 'v63', 'name' => 'Las Nieves', 'corregimiento' => 'Felidia'],
                ['code' => 'v64', 'name' => 'El Diamante', 'corregimiento' => 'Felidia'],
                ['code' => 'v65', 'name' => 'Santa Helena', 'corregimiento' => 'Felidia'],
                ['code' => 'v66', 'name' => 'El Saladito (Cabecera)', 'corregimiento' => 'El Saladito'],
                ['code' => 'v67', 'name' => 'San Antonio', 'corregimiento' => 'El Saladito'],
                ['code' => 'v68', 'name' => 'San Pablo', 'corregimiento' => 'El Saladito'],
                ['code' => 'v69', 'name' => 'San Miguel', 'corregimiento' => 'El Saladito'],
                ['code' => 'v70', 'name' => 'Montañuelas', 'corregimiento' => 'El Saladito'],
                ['code' => 'v71', 'name' => 'El Palomar', 'corregimiento' => 'El Saladito'],
                ['code' => 'v72', 'name' => 'Las Nieves', 'corregimiento' => 'El Saladito'],
                ['code' => 'v73', 'name' => 'El Cerezo', 'corregimiento' => 'El Saladito'],
                ['code' => 'v74', 'name' => 'Zoológico - Jardín Botánico', 'corregimiento' => 'El Saladito'],
                ['code' => 'v75', 'name' => 'Bajo Aguacatal', 'corregimiento' => 'El Saladito'],
                ['code' => 'v76', 'name' => 'La Elvira (Cabecera)', 'corregimiento' => 'La Elvira'],
                ['code' => 'v77', 'name' => 'Los Laureles', 'corregimiento' => 'La Elvira'],
                ['code' => 'v78', 'name' => 'Alto Aguacatal', 'corregimiento' => 'La Elvira'],
                ['code' => 'v79', 'name' => 'Kilómetro 18', 'corregimiento' => 'La Elvira'],
                ['code' => 'v80', 'name' => 'La Castilla (Cabecera)', 'corregimiento' => 'La Castilla'],
                ['code' => 'v81', 'name' => 'Las Palmas', 'corregimiento' => 'La Castilla'],
                ['code' => 'v82', 'name' => 'Limones', 'corregimiento' => 'La Castilla'],
                ['code' => 'v83', 'name' => 'Montañitas', 'corregimiento' => 'La Castilla'],
                ['code' => 'v84', 'name' => 'Las Brisas', 'corregimiento' => 'La Castilla'],
                ['code' => 'v85', 'name' => 'El Futuro', 'corregimiento' => 'La Castilla'],
                ['code' => 'v86', 'name' => 'El Pinar', 'corregimiento' => 'La Castilla'],
                ['code' => 'v87', 'name' => 'Galeras', 'corregimiento' => 'La Castilla'],
                ['code' => 'v88', 'name' => 'La Paz (Cabecera)', 'corregimiento' => 'La Paz'],
                ['code' => 'v89', 'name' => 'Villa del Rosario', 'corregimiento' => 'La Paz'],
                ['code' => 'v90', 'name' => 'Lomitas', 'corregimiento' => 'La Paz'],
                ['code' => 'v91', 'name' => 'Montebello (Cabecera)', 'corregimiento' => 'Montebello'],
                ['code' => 'v92', 'name' => 'Campoalegre', 'corregimiento' => 'Montebello'],
                ['code' => 'v93', 'name' => 'Golondrinas (Cabecera)', 'corregimiento' => 'Golondrinas'],
                ['code' => 'v94', 'name' => 'La María', 'corregimiento' => 'Golondrinas'],
                ['code' => 'v95', 'name' => 'Las Tres Cruces', 'corregimiento' => 'Golondrinas'],
            ];
        }

        return array_map(static function (array $row): array {
            $row['label'] = $row['name'].' · '.$row['corregimiento'];

            return $row;
        }, self::$rows);
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_column(self::all(), 'code');
    }

    /** @return array{code: string, name: string, corregimiento: string, label: string}|null */
    public static function match(?string $value): ?array
    {
        $needle = self::normalize($value);
        if ($needle === '') {
            return null;
        }
        foreach (self::all() as $row) {
            if (self::normalize($row['label']) === $needle || self::normalize($row['name']) === $needle || $row['code'] === $needle) {
                return $row;
            }
        }

        return null;
    }

    private static function normalize(?string $value): string
    {
        $text = mb_strtolower(trim((string) $value), 'UTF-8');
        $text = str_replace(['vereda ', 'zona rural'], '', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
