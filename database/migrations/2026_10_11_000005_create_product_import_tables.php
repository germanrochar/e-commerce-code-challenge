<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('status', 20)->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('last_processed_row')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('skipped_empty_count')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('sku', 255)->nullable();
            $table->string('column', 32)->nullable();
            $table->string('type', 20);
            $table->string('message', 500);
            $table->json('raw');

            $table->index(['product_import_id', 'row_number']);
            $table->index(['product_import_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_errors');
        Schema::dropIfExists('product_imports');
    }
};
