<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usbc_approved_ball_lists', function (Blueprint $table): void {
            $table->id();
            $table->date('official_updated_on')->nullable();
            $table->text('source_page_url');
            $table->text('source_pdf_url')->nullable();
            $table->text('source_api_url');
            $table->string('source_sha256', 64)->unique();
            $table->string('status', 32)->default('running');
            $table->timestamp('fetched_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('brand_count')->default(0);
            $table->unsignedInteger('entry_count')->default(0);
            $table->unsignedInteger('matched_catalog_count')->default(0);
            $table->unsignedInteger('ambiguous_catalog_count')->default(0);
            $table->unsignedInteger('unlisted_catalog_count')->default(0);
            $table->json('report')->nullable();
            $table->timestamps();

            $table->index(
                ['official_updated_on', 'status'],
                'usbc_ball_lists_updated_status_index'
            );
        });

        Schema::create('usbc_approved_ball_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('list_id')
                ->constrained('usbc_approved_ball_lists')
                ->cascadeOnDelete();
            $table->string('brand');
            $table->string('name');
            $table->string('approved_date_text')->nullable();
            $table->date('approved_on')->nullable();
            $table->text('image_url')->nullable();
            $table->string('normalized_brand');
            $table->string('normalized_name');
            $table->string('source_fingerprint', 64);
            $table->timestamps();

            $table->unique(
                ['list_id', 'source_fingerprint'],
                'usbc_ball_entries_list_fingerprint_unique'
            );
            $table->index(
                ['normalized_brand', 'normalized_name'],
                'usbc_ball_entries_brand_name_index'
            );
            $table->index('normalized_name', 'usbc_ball_entries_name_index');
        });

        Schema::table('approved_balls', function (Blueprint $table): void {
            $table->string('usbc_match_status', 32)
                ->default('unchecked')
                ->after('approved');
            $table->string('usbc_match_method', 64)
                ->nullable()
                ->after('usbc_match_status');
            $table->string('usbc_matched_brand')
                ->nullable()
                ->after('usbc_match_method');
            $table->string('usbc_matched_name')
                ->nullable()
                ->after('usbc_matched_brand');
            $table->json('usbc_match_candidates')
                ->nullable()
                ->after('usbc_matched_name');
            $table->timestamp('usbc_checked_at')
                ->nullable()
                ->after('usbc_match_candidates');

            $table->index('usbc_match_status', 'approved_balls_usbc_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('approved_balls', function (Blueprint $table): void {
            $table->dropIndex('approved_balls_usbc_status_index');
            $table->dropColumn([
                'usbc_match_status',
                'usbc_match_method',
                'usbc_matched_brand',
                'usbc_matched_name',
                'usbc_match_candidates',
                'usbc_checked_at',
            ]);
        });

        Schema::dropIfExists('usbc_approved_ball_entries');
        Schema::dropIfExists('usbc_approved_ball_lists');
    }
};
