<?php

namespace App\Jobs;

use App\Imports\ProductImporter;
use App\Models\ProductImport;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class ImportProducts implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'imports';

    public int $timeout = 1800;

    public int $maxExceptions = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public ProductImport $import)
    {
        $this->onQueue(self::QUEUE);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('product-imports'))->releaseAfter(15)->expireAfter($this->timeout + 60)];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(ProductImporter $importer): void
    {
        $importer->run($this->import->refresh());
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->refresh()->markFailed('The import stopped unexpectedly after several attempts. Rows processed before the failure were kept.');
    }
}
