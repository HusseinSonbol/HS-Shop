<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductSupplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('suppliers.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $offer = $this->route('offer');
        $offer = $offer instanceof ProductSupplier ? $offer : null;
        $supplier = $this->route('supplier');

        return [
            'product_id' => [
                'required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at'),
                Rule::unique('product_suppliers', 'product_id')->where(fn ($query) => $query->where('supplier_id', $supplier?->id))->ignore($offer?->product_id, 'product_id'),
            ],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999999.9999', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'supplier_availability_status' => ['required', Rule::in(['unknown', 'available', 'unavailable'])],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $supplier = $this->route('supplier');
            $offer = $this->route('offer');
            if ($offer instanceof ProductSupplier && $supplier && (int) $offer->supplier_id !== (int) $supplier->id) {
                $validator->errors()->add('product_id', __('admin.suppliers.errors.offer_supplier_mismatch'));
            }
            if ($offer instanceof ProductSupplier && (int) $offer->product_id !== (int) $this->input('product_id')) {
                $validator->errors()->add('product_id', __('admin.suppliers.validation.product_change_not_allowed'));
            }
            if (! $offer instanceof ProductSupplier && $supplier && ! $supplier->is_active) {
                $validator->errors()->add('supplier_id', __('admin.suppliers.errors.supplier_inactive'));
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.required' => __('admin.suppliers.validation.product_required'),
            'product_id.exists' => __('admin.suppliers.validation.product_invalid'),
            'product_id.unique' => __('admin.suppliers.validation.duplicate_offer'),
            'unit_cost.required' => __('admin.suppliers.validation.cost_required'),
            'unit_cost.numeric' => __('admin.suppliers.validation.cost_invalid'),
            'unit_cost.min' => __('admin.suppliers.validation.cost_invalid'),
            'unit_cost.max' => __('admin.suppliers.validation.cost_invalid'),
            'unit_cost.regex' => __('admin.suppliers.validation.cost_precision'),
            'supplier_availability_status.required' => __('admin.suppliers.validation.availability_required'),
            'supplier_availability_status.in' => __('admin.suppliers.validation.availability_invalid'),
            'valid_from.date' => __('admin.suppliers.validation.date_invalid'),
            'valid_until.date' => __('admin.suppliers.validation.date_invalid'),
            'valid_until.after_or_equal' => __('admin.suppliers.validation.validity_order'),
            'is_active.required' => __('admin.suppliers.validation.status_required'),
            'is_active.boolean' => __('admin.suppliers.validation.status_invalid'),
            'change_reason.max' => __('admin.suppliers.validation.reason_max'),
        ];
    }
}
