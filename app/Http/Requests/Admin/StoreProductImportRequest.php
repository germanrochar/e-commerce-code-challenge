<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreProductImportRequest extends FormRequest
{
    public const int MAX_KILOBYTES = 50 * 1024;

    public function authorize(): bool
    {
        return $this->user()?->can('import', Product::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload a .csv file.',
            'file.mimetypes' => 'Upload a plain-text CSV file.',
            'file.max' => 'The file may not be larger than 50 MB.',
        ];
    }

    public function csv(): UploadedFile
    {
        $file = $this->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
