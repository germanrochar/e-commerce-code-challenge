<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Validation\ProductInput;
use App\Validation\ProductRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return ProductRules::forForm($product instanceof Product ? $product : null);
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
