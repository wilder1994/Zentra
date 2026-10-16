<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\SecurityCompany;
use App\Models\User;
use App\Services\Platform\ScheduleCompanyPackageChangeService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class AdminExpandProductTest extends TestCase
{
    private function setUpCompany(): array
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        return [$admin, $company];
    }

    public function test_expands_access_now_with_agreed_amount_and_reason(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $before = (int) $company->accessSeats()->size();

        $this->actingAs($admin)
            ->post(route('admin.companies.plan.expand', $company), [
                'product' => 'access',
                'quantity' => 3,
                'agreed_amount' => 999000,
                'expand_note' => 'Acuerdo comercial con gerencia',
                'apply_when' => 'now',
            ])
            ->assertRedirect(route('admin.companies.show', $company))
            ->assertSessionHas('success');

        $company->refresh();
        $this->assertSame($before + 3, $company->accessSeats()->size());
        $this->assertEqualsWithDelta(999000, $company->contractedAmount(), 0.01);
        $this->assertTrue($company->price_locked);
        $this->assertSame('Acuerdo comercial con gerencia', $company->price_lock_note);

        $change = $company->priceChanges()->latest('id')->firstOrFail();
        $this->assertSame('quota_expansion', $change->reason);
        $this->assertSame('Acuerdo comercial con gerencia', $change->note);
    }

    public function test_scheduled_expansion_applies_agreed_amount_when_due(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $before = (int) $company->accessSeats()->size();
        $date = CarbonImmutable::now()->addDays(5)->toDateString();

        $this->actingAs($admin)
            ->post(route('admin.companies.plan.expand', $company), [
                'product' => 'access',
                'quantity' => 2,
                'agreed_amount' => 500000,
                'expand_note' => 'Ampliación al corte',
                'apply_when' => 'on_date',
                'effective_on' => $date,
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $company->refresh();
        $this->assertSame($before, $company->accessSeats()->size());
        $this->assertEqualsWithDelta(500000, (float) $company->scheduled_amount, 0.01);
        $this->assertSame('access', $company->scheduled_amount_product);

        app(ScheduleCompanyPackageChangeService::class)->applyDueChanges(CarbonImmutable::parse($date)->addHour());

        $company->refresh();
        $this->assertSame($before + 2, $company->accessSeats()->size());
        $this->assertEqualsWithDelta(500000, $company->contractedAmount(), 0.01);
        $this->assertNull($company->scheduled_amount);
        $this->assertSame('quota_expansion', $company->priceChanges()->latest('id')->value('reason'));
    }

    public function test_requires_reason_and_contracted_product(): void
    {
        [$admin, $company] = $this->setUpCompany();
        $company->forceFill(['observatory_seats' => 0])->save();

        $this->actingAs($admin)
            ->post(route('admin.companies.plan.expand', $company), [
                'product' => 'observatory',
                'quantity' => 1,
                'agreed_amount' => 1000,
                'expand_note' => 'x',
                'apply_when' => 'now',
            ])
            ->assertSessionHasErrors('product');

        $this->actingAs($admin)
            ->post(route('admin.companies.plan.expand', $company), [
                'product' => 'access',
                'quantity' => 1,
                'agreed_amount' => 1000,
                'apply_when' => 'now',
            ])
            ->assertSessionHasErrors('expand_note');
    }

    public function test_show_page_renders_product_bars(): void
    {
        [$admin, $company] = $this->setUpCompany();

        $this->actingAs($admin)
            ->get(route('admin.companies.show', $company))
            ->assertOk()
            ->assertSee('Ampliar producto del plan')
            ->assertSee('Aplicar al corte', false);
    }
}
