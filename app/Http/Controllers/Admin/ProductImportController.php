<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportErrorType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductImportRequest;
use App\Http\Resources\Paginated;
use App\Http\Resources\ProductImportErrorResource;
use App\Http\Resources\ProductImportResource;
use App\Jobs\ImportProducts;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportError;
use App\Validation\ProductInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    private const string DISK = 'local';

    public function index(): Response
    {
        Gate::authorize('import', Product::class);

        $imports = ProductImport::query()
            ->with('user:id,name')
            ->latest('id')
            ->paginate(10);

        return Inertia::render('admin/imports/Index', [
            'imports' => Paginated::of($imports, ProductImportResource::class),
            'requiredColumns' => ProductInput::FIELDS,
            'maxUploadMb' => StoreProductImportRequest::MAX_KILOBYTES / 1024,
        ]);
    }

    public function store(StoreProductImportRequest $request): RedirectResponse
    {
        $file = $request->csv();
        $path = $file->storeAs('imports', Str::uuid7().'.csv', self::DISK);

        abort_if($path === false, 500, 'The file could not be stored.');

        $import = ProductImport::queue(self::DISK, $path, $file->getClientOriginalName(), $request->user());

        ImportProducts::dispatch($import);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Import queued. You can follow its progress here.']);

        return to_route('admin.imports.show', $import);
    }

    public function show(Request $request, ProductImport $import): Response
    {
        Gate::authorize('import', Product::class);

        $type = ImportErrorType::tryFrom((string) $request->query('type'));

        $errors = $import->errors()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderBy('row_number')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/imports/Show', [
            'import' => ProductImportResource::make($import->load('user:id,name'))->resolve(),
            'errors' => Paginated::of($errors, ProductImportErrorResource::class),
            'filters' => ['type' => $type?->value],
        ]);
    }

    public function rejected(ProductImport $import): StreamedResponse
    {
        Gate::authorize('import', Product::class);

        $filename = pathinfo($import->original_filename, PATHINFO_FILENAME).'-rejected-rows.csv';

        return response()->streamDownload(function () use ($import): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['row_number', ...ProductInput::FIELDS, 'errors'], escape: '');

            $flush = function (?int $row, array $raw, array $messages) use ($out): void {
                if ($row === null) {
                    return;
                }

                $values = array_map(fn (string $field) => $raw[$field] ?? '', ProductInput::FIELDS);
                fputcsv($out, [$row, ...$values, implode(' | ', $messages)], escape: '');
            };

            $currentRow = null;
            $raw = [];
            $messages = [];

            $errors = ProductImportError::query()
                ->where('product_import_id', $import->getKey())
                ->orderBy('row_number')
                ->orderBy('id')
                ->lazy(1000);

            foreach ($errors as $error) {
                if ($error->row_number !== $currentRow) {
                    $flush($currentRow, $raw, $messages);
                    $currentRow = $error->row_number;
                    $raw = $error->raw;
                    $messages = [];
                }

                $messages[] = $error->message;
            }

            $flush($currentRow, $raw, $messages);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('import', Product::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ProductInput::FIELDS, escape: '');
            fputcsv($out, ['Running Shoes', 'RS-001', 'Lightweight running shoes for daily training', 'Footwear', '89.99', '150', '0.35'], escape: '');
            fclose($out);
        }, 'products-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
