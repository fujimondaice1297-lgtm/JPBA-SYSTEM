<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ball_manufacturers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('base_url');
            $table->text('catalog_url');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('approved_balls', function (Blueprint $table): void {
            $table->foreignId('manufacturer_id')
                ->nullable()
                ->after('manufacturer')
                ->constrained('ball_manufacturers')
                ->nullOnDelete();
            $table->string('brand')->nullable()->after('manufacturer_id');
            $table->string('sort_name')->nullable()->after('name_kana');
            $table->string('source_key', 64)->nullable()->after('approved');
            $table->text('source_url')->nullable()->after('source_key');
            $table->text('source_image_url')->nullable()->after('source_url');
            $table->text('image_path')->nullable()->after('source_image_url');
            $table->string('image_sha256', 64)->nullable()->after('image_path');
            $table->string('catalog_status', 32)->default('listed')->after('image_sha256');
            $table->json('source_payload')->nullable()->after('catalog_status');
            $table->string('source_fingerprint', 64)->nullable()->after('source_payload');
            $table->timestamp('first_seen_at')->nullable()->after('source_fingerprint');
            $table->timestamp('last_seen_at')->nullable()->after('first_seen_at');
            $table->timestamp('imported_at')->nullable()->after('last_seen_at');
            $table->timestamp('image_imported_at')->nullable()->after('imported_at');

            $table->unique('source_key', 'approved_balls_source_key_unique');
            $table->index(
                ['manufacturer_id', 'brand', 'catalog_status'],
                'approved_balls_catalog_filter_index'
            );
            $table->index('sort_name', 'approved_balls_sort_name_index');
        });

        Schema::create('ball_catalog_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manufacturer_id')
                ->nullable()
                ->constrained('ball_manufacturers')
                ->nullOnDelete();
            $table->string('mode', 32)->default('full');
            $table->string('status', 32)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('image_downloaded_count')->default(0);
            $table->unsignedInteger('image_reused_count')->default(0);
            $table->unsignedInteger('image_failed_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('cursor_url')->nullable();
            $table->json('report')->nullable();
            $table->timestamps();

            $table->index(
                ['manufacturer_id', 'started_at'],
                'ball_catalog_runs_manufacturer_started_index'
            );
            $table->index('status', 'ball_catalog_runs_status_index');
        });

        Schema::create('ball_catalog_import_failures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_run_id')
                ->constrained('ball_catalog_import_runs')
                ->cascadeOnDelete();
            $table->foreignId('manufacturer_id')
                ->nullable()
                ->constrained('ball_manufacturers')
                ->nullOnDelete();
            $table->string('phase', 32);
            $table->text('page_url')->nullable();
            $table->text('product_url')->nullable();
            $table->text('image_url')->nullable();
            $table->text('error_message');
            $table->unsignedSmallInteger('attempt_count')->default(1);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(
                ['manufacturer_id', 'phase'],
                'ball_catalog_failures_manufacturer_phase_index'
            );
            $table->index('resolved_at', 'ball_catalog_failures_resolved_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ball_catalog_import_failures');
        Schema::dropIfExists('ball_catalog_import_runs');

        Schema::table('approved_balls', function (Blueprint $table): void {
            $table->dropUnique('approved_balls_source_key_unique');
            $table->dropIndex('approved_balls_catalog_filter_index');
            $table->dropIndex('approved_balls_sort_name_index');
            $table->dropConstrainedForeignId('manufacturer_id');
            $table->dropColumn([
                'brand',
                'sort_name',
                'source_key',
                'source_url',
                'source_image_url',
                'image_path',
                'image_sha256',
                'catalog_status',
                'source_payload',
                'source_fingerprint',
                'first_seen_at',
                'last_seen_at',
                'imported_at',
                'image_imported_at',
            ]);
        });

        Schema::dropIfExists('ball_manufacturers');
    }
};
