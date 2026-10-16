<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\CommercialProduct;
use App\Models\SecurityCompany;
use App\Services\Platform\ApplyAdminPlanChangeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ExpandCompanyProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('platform.companies.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product' => ['required', Rule::enum(CommercialProduct::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CommercialProduct::MAX_EMPLOYEES],
            'agreed_amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'expand_note' => ['required', 'string', 'max:255'],
            'apply_when' => ['required', Rule::in([
                ApplyAdminPlanChangeService::WHEN_NOW,
                ApplyAdminPlanChangeService::WHEN_ON_DATE,
                ApplyAdminPlanChangeService::WHEN_PERIOD_END,
            ])],
            'effective_on' => [
                'nullable',
                'date',
                'after_or_equal:today',
                Rule::requiredIf($this->input('apply_when') === ApplyAdminPlanChangeService::WHEN_ON_DATE),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $company = $this->route('company');
            $product = $this->product();
            if (! $company instanceof SecurityCompany || PlanSelection::fromCompany($company)->seats($product) < 1) {
                $validator->errors()->add('product', 'La empresa no tiene contratado ese producto.');

                return;
            }
            $current = PlanSelection::fromCompany($company)->seats($product);
            if ($current + (int) $this->validated('quantity') > $product->maxQuantity()) {
                $validator->errors()->add('quantity', 'La cantidad supera el máximo permitido.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'product' => 'producto',
            'quantity' => 'cantidad',
            'agreed_amount' => 'valor pactado',
            'expand_note' => 'motivo',
            'apply_when' => 'cuándo aplica',
            'effective_on' => 'fecha',
        ];
    }

    public function product(): CommercialProduct
    {
        return CommercialProduct::from((string) $this->input('product'));
    }

    public function expandedSelection(SecurityCompany $company): PlanSelection
    {
        $current = PlanSelection::fromCompany($company);
        $add = (int) $this->validated('quantity');

        return match ($this->product()) {
            CommercialProduct::Access => new PlanSelection(
                new AccessSeatSplit($current->access->manual + $add, $current->access->hardware),
                $current->supervision,
                $current->indexing,
                $current->observatory,
            ),
            CommercialProduct::Supervision => new PlanSelection($current->access, $current->supervision + $add, $current->indexing, $current->observatory),
            CommercialProduct::Indexing => new PlanSelection($current->access, $current->supervision, $current->indexing + $add, $current->observatory),
            CommercialProduct::Observatory => new PlanSelection($current->access, $current->supervision, $current->indexing, $current->observatory + $add),
        };
    }
}
