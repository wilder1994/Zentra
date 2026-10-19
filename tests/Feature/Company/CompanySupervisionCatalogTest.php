<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\SupervisorAlarmType;
use App\Models\SupervisorChecklistItem;
use App\Models\SupervisorControlBookType;
use App\Models\SupervisorDocumentType;
use App\Models\SupervisorShiftTemplate;
use App\Models\SupervisorSupportType;
use App\Models\SupervisorWeaponBrand;
use App\Models\SupervisorWeaponType;
use App\Models\SupervisorRiskType;
use App\Models\SupervisorZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanySupervisionCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_manage_supervision_catalogs(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('company.supervision-zones.index'))
            ->assertOk()
            ->assertSee('Norte')
            ->assertSee('Zonas');

        $this->actingAs($admin)->post(route('company.supervision-zones.store'), [
            'name' => 'Occidente',
            'is_active' => '1',
        ])->assertRedirect(route('company.supervision-zones.index'));

        $this->assertDatabaseHas('supervisor_zones', [
            'name' => 'Occidente',
            'security_company_id' => $admin->security_company_id,
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision-shifts.index'))
            ->assertOk()
            ->assertSee('Día')
            ->assertSee('Noche');

        $this->actingAs($admin)->post(route('company.supervision-shifts.store'), [
            'name' => 'Tarde',
            'starts_at' => '14:00',
            'ends_at' => '22:00',
            'is_active' => '1',
        ])->assertRedirect(route('company.supervision-shifts.index'));

        $this->assertDatabaseHas('supervisor_shift_templates', [
            'name' => 'Tarde',
            'starts_at' => '14:00',
            'ends_at' => '22:00',
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision-preop.index'))
            ->assertOk()
            ->assertSee('Casco')
            ->assertSee('Luces');

        $this->actingAs($admin)->post(route('company.supervision-preop.store'), [
            'kind' => 'ppe',
            'name' => 'Gafas de seguridad',
        ])->assertRedirect(route('company.supervision-preop.index'));

        $this->assertDatabaseHas('supervisor_checklist_items', [
            'name' => 'Gafas de seguridad',
            'kind' => 'ppe',
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision-document-types.index'))
            ->assertOk()
            ->assertSee('Tipos de documento')
            ->assertSee('Documentos');

        $this->actingAs($admin)->post(route('company.supervision-document-types.store'), [
            'name' => 'Carta de notificación',
            'is_active' => '1',
        ])->assertRedirect(route('company.supervision-document-types.index'));

        $this->assertDatabaseHas('supervisor_document_types', [
            'name' => 'Carta de notificación',
            'security_company_id' => $admin->security_company_id,
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision-control-book-types.index'))
            ->assertOk()
            ->assertSee('Tipos de libro de control')
            ->assertSee('Libros');

        $this->actingAs($admin)->post(route('company.supervision-control-book-types.store'), [
            'name' => 'Minuta',
            'is_active' => '1',
        ])->assertRedirect(route('company.supervision-control-book-types.index'));

        $this->assertDatabaseHas('supervisor_control_book_types', [
            'name' => 'Minuta',
            'security_company_id' => $admin->security_company_id,
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision-weapon-types.index'))
            ->assertOk()
            ->assertSee('Tipos de arma');

        $this->actingAs($admin)->post(route('company.supervision-weapon-types.store'), [
            'name' => 'Pistola',
            'is_active' => '1',
        ])->assertRedirect(route('company.supervision-weapon-types.index'));

        $this->assertDatabaseHas('supervisor_weapon_types', [
            'name' => 'Pistola',
            'security_company_id' => $admin->security_company_id,
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision-weapon-brands.index'))
            ->assertOk()
            ->assertSee('Marcas de arma');

        $this->actingAs($admin)->post(route('company.supervision-weapon-brands.store'), [
            'name' => 'Glock',
            'is_active' => '1',
        ])->assertRedirect(route('company.supervision-weapon-brands.index'));

        $this->assertDatabaseHas('supervisor_weapon_brands', [
            'name' => 'Glock',
            'security_company_id' => $admin->security_company_id,
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision-risk-types.index'))
            ->assertOk()
            ->assertSee('Tipos de riesgo')
            ->assertSee('Riesgos');

        $this->actingAs($admin)->post(route('company.supervision-risk-types.store'), [
            'name' => 'Riesgo físico',
            'is_active' => '1',
        ])->assertRedirect(route('company.supervision-risk-types.index'));

        $this->assertDatabaseHas('supervisor_risk_types', [
            'name' => 'Riesgo físico',
            'security_company_id' => $admin->security_company_id,
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision-alarm-types.index'))
            ->assertOk()
            ->assertSee('Tipos de alarma');

        $this->actingAs($admin)->post(route('company.supervision-alarm-types.store'), [
            'name' => 'Pánico',
            'is_active' => '1',
        ])->assertRedirect(route('company.supervision-alarm-types.index'));

        $this->assertDatabaseHas('supervisor_alarm_types', [
            'name' => 'Pánico',
            'security_company_id' => $admin->security_company_id,
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision-support-types.index'))
            ->assertOk()
            ->assertSee('Tipos de apoyo');

        $this->actingAs($admin)->post(route('company.supervision-support-types.store'), [
            'name' => 'Refuerzo de puesto',
            'is_active' => '1',
        ])->assertRedirect(route('company.supervision-support-types.index'));

        $this->assertDatabaseHas('supervisor_support_types', [
            'name' => 'Refuerzo de puesto',
            'security_company_id' => $admin->security_company_id,
        ]);
    }

    public function test_guard_cannot_access_supervision_catalogs(): void
    {
        $this->seedWithPilot();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();

        $this->actingAs($guard)->get(route('company.supervision-zones.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('company.supervision-shifts.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('company.supervision-preop.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('company.supervision-document-types.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('company.supervision-control-book-types.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('company.supervision-weapon-types.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('company.supervision-weapon-brands.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('company.supervision-risk-types.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('company.supervision-alarm-types.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('company.supervision-support-types.index'))->assertForbidden();
    }

    public function test_company_admin_can_deactivate_zone_and_template(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $zone = SupervisorZone::query()
            ->where('security_company_id', $admin->security_company_id)
            ->where('name', 'Norte')
            ->firstOrFail();
        $template = SupervisorShiftTemplate::query()
            ->where('security_company_id', $admin->security_company_id)
            ->where('name', 'Día')
            ->firstOrFail();
        $item = SupervisorChecklistItem::query()
            ->where('security_company_id', $admin->security_company_id)
            ->where('name', 'Casco')
            ->firstOrFail();
        $docType = SupervisorDocumentType::query()->create([
            'security_company_id' => $admin->security_company_id,
            'name' => 'Oficio',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $bookType = SupervisorControlBookType::query()->create([
            'security_company_id' => $admin->security_company_id,
            'name' => 'Novedades',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $weaponType = SupervisorWeaponType::query()->create([
            'security_company_id' => $admin->security_company_id,
            'name' => 'Escopeta',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $weaponBrand = SupervisorWeaponBrand::query()->create([
            'security_company_id' => $admin->security_company_id,
            'name' => 'Remington',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $riskType = SupervisorRiskType::query()->create([
            'security_company_id' => $admin->security_company_id,
            'name' => 'Riesgo eléctrico',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $alarmType = SupervisorAlarmType::query()->create([
            'security_company_id' => $admin->security_company_id,
            'name' => 'Intrusión',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $supportType = SupervisorSupportType::query()->create([
            'security_company_id' => $admin->security_company_id,
            'name' => 'Escolta',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)->put(route('company.supervision-zones.update', $zone), [
            'name' => 'Norte',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-zones.index'));

        $this->actingAs($admin)->put(route('company.supervision-shifts.update', $template), [
            'name' => 'Día',
            'starts_at' => '06:00',
            'ends_at' => '18:00',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-shifts.index'));

        $this->actingAs($admin)->put(route('company.supervision-preop.update', $item), [
            'name' => 'Casco',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-preop.index'));

        $this->actingAs($admin)->put(route('company.supervision-document-types.update', $docType), [
            'name' => 'Oficio',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-document-types.index'));

        $this->actingAs($admin)->put(route('company.supervision-control-book-types.update', $bookType), [
            'name' => 'Novedades',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-control-book-types.index'));

        $this->actingAs($admin)->put(route('company.supervision-weapon-types.update', $weaponType), [
            'name' => 'Escopeta',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-weapon-types.index'));

        $this->actingAs($admin)->put(route('company.supervision-weapon-brands.update', $weaponBrand), [
            'name' => 'Remington',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-weapon-brands.index'));

        $this->actingAs($admin)->put(route('company.supervision-risk-types.update', $riskType), [
            'name' => 'Riesgo eléctrico',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-risk-types.index'));

        $this->actingAs($admin)->put(route('company.supervision-alarm-types.update', $alarmType), [
            'name' => 'Intrusión',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-alarm-types.index'));

        $this->actingAs($admin)->put(route('company.supervision-support-types.update', $supportType), [
            'name' => 'Escolta',
            'is_active' => '0',
        ])->assertRedirect(route('company.supervision-support-types.index'));

        $this->assertFalse($zone->fresh()->is_active);
        $this->assertFalse($template->fresh()->is_active);
        $this->assertFalse($item->fresh()->is_active);
        $this->assertFalse($docType->fresh()->is_active);
        $this->assertFalse($bookType->fresh()->is_active);
        $this->assertFalse($weaponType->fresh()->is_active);
        $this->assertFalse($weaponBrand->fresh()->is_active);
        $this->assertFalse($riskType->fresh()->is_active);
        $this->assertFalse($alarmType->fresh()->is_active);
        $this->assertFalse($supportType->fresh()->is_active);
    }
}
