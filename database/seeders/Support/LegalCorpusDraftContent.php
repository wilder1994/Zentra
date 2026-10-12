<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Enums\CommercialMetal;
use App\Enums\PackageModality;
use App\Support\Privacy\MinorPersonalData;

/**
 * Borradores de texto legal para desarrollo/QA.
 * Revisión jurídica externa pendiente antes de go-live.
 */
final class LegalCorpusDraftContent
{
    public static function terms(): string
    {
        return <<<'TXT'
TÉRMINOS Y CONDICIONES DE USO — PLATAFORMA ZENTRA (borrador)

1. Objeto. Estos términos regulan el acceso y uso de la plataforma SaaS Zentra para gestión de control de acceso, visitantes y operaciones asociadas, prestada bajo el paquete comercial contratado.

2. Capacidad. El suscriptor declara actuar por sí o mediante representante legal con facultades suficientes (persona natural o jurídica).

3. Cuenta y acceso. El suscriptor es responsable de credenciales, usuarios autorizados y del uso conforme a la ley colombiana y a las políticas publicadas en la Normoteca.

4. Disponibilidad. El servicio se presta en modalidad best-effort según el plan. Mantenimientos programados o fuerza mayor pueden afectar temporalmente la disponibilidad.

5. Pagos. El ciclo de facturación (mensual/anual) y el precio del SKU contratado constan en la oferta aceptada. Mora puede activar el procedimiento de gracia, suspensión y archivo descrito en la Normoteca.

6. Propiedad intelectual. Zentra y sus licenciantes conservan derechos sobre el software, marcas y documentación. Se otorga licencia de uso no exclusiva, intransferible y limitada al paquete adquirido.

7. Responsabilidad. En la máxima medida permitida por la ley, la responsabilidad de Zentra se limita a los montos efectivamente pagados en los últimos doce (12) meses por el servicio afectado, salvo dolo o culpa grave.

8. Datos personales. El tratamiento se rige por la Política de Tratamiento de Datos y, cuando aplique, por acuerdos de encargado/responsable conforme a la Ley 1581 de 2012 y el Decreto 1377 de 2013.

9. Mensajes de datos. Las aceptaciones electrónicas (clickwrap) producen efectos jurídicos conforme a la Ley 527 de 1999 y normas complementarias.

10. Ley y jurisdicción. Se aplica la legislación de la República de Colombia. Controversias se someterán a los jueces competentes del domicilio del prestador, sin perjuicio de mecanismos alternativos acordados.

Versión inicial para desarrollo y pruebas. Revisión legal pendiente antes del go-live comercial.
TXT;
    }

    public static function minorsDataPolicy(): string
    {
        return MinorPersonalData::NOTICE;
    }

    public static function privacy(): string
    {
        return <<<'TXT'
POLÍTICA DE TRATAMIENTO DE DATOS PERSONALES — ZENTRA (borrador)

1. Responsable. Zentra (en adelante el Responsable) trata datos personales en el marco de la prestación del servicio SaaS y de la relación comercial con suscriptores, conforme a la Ley 1581 de 2012, el Decreto 1377 de 2013 y normas complementarias.

2. Alcance. Aplica a datos de representantes legales, usuarios de plataforma, y —cuando el suscriptor lo configure— a datos de visitantes, residentes u otros titulares tratados en el tenant, en los roles que correspondan (responsable o encargado).

3. Finalidades. (i) Gestionar la suscripción, facturación y soporte; (ii) autenticar usuarios y auditar accesos; (iii) prestar funcionalidades de control de acceso y bitácoras; (iv) cumplir obligaciones legales y requerimientos de autoridad competente; (v) mejorar seguridad y calidad del servicio.

4. Derechos ARCO. Los titulares pueden conocer, actualizar, rectificar y solicitar la supresión de sus datos, y revocar la autorización cuando proceda, canalizando la solicitud al correo de contacto publicado o al administrador del tenant cuando el tratamiento sea del suscriptor.

5. Encargo. Cuando Zentra trate datos por cuenta del suscriptor (encargado), lo hará según instrucciones documentadas, medidas de seguridad razonables y retención acordada. El suscriptor garantiza bases legales frente a titulares.

6. Conservación. Soportes mercantiles y contractuales: plazos mercantiles aplicables (~10 años referencia CCom / Ley 962). Censo operativo del tenant: retención corta post-baja según TRD y política de ciclo de vida (gracia → suspensión → archivo → purga).

7. Seguridad. Se aplican controles técnicos y organizativos proporcionales al riesgo (acceso restringido, trazabilidad, copias de respaldo según arquitectura vigente).

8. Transferencias. No se realiza transferencia internacional de datos salvo proveedores necesarios para la operación del servicio, con salvaguardas contractuales adecuadas.

9. Autorización. La aceptación de esta política y/o el uso de la plataforma constituyen autorización cuando la ley lo exige, sin perjuicio de avisos específicos en formularios.

Versión inicial para desarrollo y pruebas. Revisión legal pendiente antes del go-live comercial.
TXT;
    }

    public static function procedureLifecycle(): string
    {
        return <<<'TXT'
PROCEDIMIENTO DE GRACIA, SUSPENSIÓN, ARCHIVO Y PURGA — ZENTRA (borrador)

1. Propósito. Definir el ciclo operativo ante falta de pago o retiro del servicio, preservando evidencias mercantiles y cumpliendo principios de minimización (Ley 1581).

2. Gracia. Tras vencimiento sin pago, se otorga un periodo de gracia de cinco (5) días calendario (configurable en producto). Durante gracia el servicio permanece activo con notificaciones de cobro.

3. Suspensión (bloqueo). Vencida la gracia sin regularización, se suspende el acceso operativo del tenant. Los datos permanecen retenidos; no hay purga en esta etapa.

4. Archivo por falta de pago. Transcurrido el plazo configurable de suspensión sin pago, el expediente comercial se archiva. Se genera evidencia/acta de ciclo en el expediente del suscriptor.

5. Retiro conjunto / cancelación. A solicitud del suscriptor o por terminación contractual, se documenta la baja y se inicia retención dual: (a) soportes mercantiles/contrato/FE según plazos legales; (b) censo operativo sujeto a purga.

6. Retención legal. Contratos, aceptaciones, facturas y actas se conservan en expediente de plataforma según TRD (~10 años de referencia mercantil). No se modifican tras aceptación (inmutabilidad del corpus congelado).

7. Purga operativa. Tras el periodo de retención operativa del censo tenant (referencia ~365 días post-baja, sujeto a TRD), se procede a eliminación o anonimización certificada de datos operativos no sujetos a conservación mercantil, con evidencia de purga.

8. Reactivación. El pago o acuerdo de regularización puede reactivar el servicio según políticas comerciales vigentes, sin alterar el historial de evidencias ya registradas.

Versión inicial para desarrollo y pruebas. Revisión legal pendiente antes del go-live comercial.
TXT;
    }

    public static function contractForPlan(CommercialMetal $metal, PackageModality $modality): string
    {
        $label = 'Plan '.$metal->label().' · '.$modality->label();
        $range = $metal->rangeLabel();
        $modalityLabel = $modality->label();

        return <<<TXT
CONTRATO DE LICENCIA DE USO SaaS — ZENTRA (borrador)
{$label}

CLÁUSULA PRIMERA — PARTES. El prestador (Zentra) y el suscriptor (persona natural o jurídica identificada en el proceso de contratación), quien declara capacidad y, si actúa por representante, facultades suficientes.

CLÁUSULA SEGUNDA — OBJETO. Licencia de uso no exclusiva e intransferible de la plataforma Zentra para el número de instalaciones contratado dentro del plan {$metal->label()} ({$range}) en modalidad {$modalityLabel}, conforme a las funcionalidades del plan publicado al momento de la aceptación.

CLÁUSULA TERCERA — PRECIO Y CICLO. El precio y el ciclo de facturación (mensual o anual) son los aceptados en la oferta / checkout. Impuestos aplicables según normativa tributaria vigente.

CLÁUSULA CUARTA — VIGENCIA. Inicia con la aceptación electrónica y el alta del tenant, y se renueva según el ciclo contratado salvo terminación conforme a estos términos y al procedimiento de ciclo de vida.

CLÁUSULA QUINTA — OBLIGACIONES DEL SUSCRIPTOR. Usar el servicio conforme a la ley; configurar y custodiar usuarios; garantizar bases legales del tratamiento de datos en su tenant; no vulnerar seguridad ni propiedad intelectual de Zentra.

CLÁUSULA SEXTA — OBLIGACIONES DEL PRESTADOR. Poner a disposición el servicio según el SKU; mantener medidas de seguridad razonables; publicar y versionar textos en Normoteca; conservar el corpus aceptado de forma inmutable en el expediente del suscriptor.

CLÁUSULA SÉPTIMA — ACEPTACIÓN ELECTRÓNICA. La aceptación clickwrap del representante legal, con sello de tiempo, dirección IP, agente de usuario y hash del corpus, constituye consentimiento y prueba del acuerdo (Ley 527 de 1999).

CLÁUSULA OCTAVA — INMUTABILIDAD. El texto aceptado queda congelado en el expediente. Ediciones posteriores de la Normoteca no modifican el contrato ni documentos globales ya aceptados por este suscriptor.

CLÁUSULA NOVENA — TERMINACIÓN Y CICLO DE VIDA. Mora, suspensión, archivo y purga se rigen por el procedimiento publicado. La terminación no extingue obligaciones de pago adeudadas ni deberes de conservación mercantil.

CLÁUSULA DÉCIMA — LEY APLICABLE. República de Colombia. Controversias ante jueces competentes del domicilio del prestador, salvo acuerdo distinto.

Anexo. Este documento forma un solo corpus de aceptación junto con Términos y Condiciones, Política de Tratamiento de Datos, Protección de datos de menores y Procedimiento de ciclo de vida vigentes al momento del clickwrap.

Versión inicial para desarrollo y pruebas. Revisión legal pendiente antes del go-live comercial.
TXT;
    }
}
