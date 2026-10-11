<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Validation\ProductInput;
use App\Validation\ProductRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    public function rules(): array
    {
        return ProductRules::forForm();
    }

    public function messages(): array
    {
        return ProductRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(ProductInput::normalize($this->all()));
    }
}
