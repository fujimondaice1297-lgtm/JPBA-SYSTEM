<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_official_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->unsignedSmallInteger('edition_number');
            $table->string('title');
            $table->date('valid_from');
            $table->date('valid_through');
            $table->text('source_page_url')->nullable();
            $table->text('source_url');
            $table->timestamp('source_published_at');
            $table->string('source_sha256', 64)->unique();
            $table->boolean('is_current')->default(true);
            $table->string('sync_status', 20)->default('ready');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('male_count')->default(0);
            $table->unsignedInteger('female_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->unsignedInteger('inactive_count')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['edition_number', 'is_current'], 'training_official_lists_edition_current_index');
            $table->index(['valid_from', 'valid_through'], 'training_official_lists_validity_index');
        });

        Schema::create('training_official_list_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_official_list_id')
                ->constrained('training_official_lists')
                ->cascadeOnDelete();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->char('gender', 1);
            $table->unsignedInteger('license_no_num');
            $table->unsignedInteger('source_order');
            $table->string('source_name')->nullable();
            $table->string('match_status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['training_official_list_id', 'gender', 'license_no_num'],
                'training_official_list_entries_list_gender_license_unique'
            );
            $table->index(
                ['pro_bowler_id', 'training_official_list_id'],
                'training_official_list_entries_bowler_list_index'
            );
            $table->index(
                ['training_official_list_id', 'match_status'],
                'training_official_list_entries_list_match_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_official_list_entries');
        Schema::dropIfExists('training_official_lists');
    }
};
