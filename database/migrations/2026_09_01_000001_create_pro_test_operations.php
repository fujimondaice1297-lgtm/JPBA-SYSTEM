<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_test_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->string('name');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('application_start')->nullable();
            $table->date('application_end')->nullable();
            $table->string('male_generation')->nullable();
            $table->string('female_generation')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('public_summary')->nullable();
            $table->timestamp('final_results_published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'name'], 'pro_test_events_year_name_unique');
        });

        Schema::create('pro_test_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_test_event_id')->constrained('pro_test_events')->cascadeOnDelete();
            $table->string('gender', 1);
            $table->string('stage_code');
            $table->string('stage_label');
            $table->unsignedSmallInteger('day_number');
            $table->date('test_date')->nullable();
            $table->string('venue')->nullable();
            $table->unsignedSmallInteger('game_start');
            $table->unsignedSmallInteger('game_end');
            $table->decimal('pass_average', 6, 2)->nullable();
            $table->boolean('is_stage_final')->default(false);
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['pro_test_event_id', 'gender', 'stage_code', 'day_number'],
                'pro_test_sessions_event_gender_stage_day_unique'
            );
            $table->index(
                ['pro_test_event_id', 'gender', 'stage_code', 'sort_order'],
                'pro_test_sessions_event_stage_sort_idx'
            );
        });

        Schema::create('pro_test_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_test_event_id')->constrained('pro_test_events')->cascadeOnDelete();
            $table->string('exam_number');
            $table->string('gender', 1)->index();
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->string('resident_prefecture')->nullable();
            $table->string('handedness', 20)->nullable();
            $table->string('final_result')->default('pending')->index();
            $table->string('license_no')->nullable();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['pro_test_event_id', 'exam_number'],
                'pro_test_candidates_event_exam_unique'
            );
        });

        Schema::create('pro_test_scores_v2', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_test_session_id')->constrained('pro_test_sessions')->cascadeOnDelete();
            $table->foreignId('pro_test_candidate_id')->constrained('pro_test_candidates')->cascadeOnDelete();
            $table->unsignedSmallInteger('game_number');
            $table->unsignedSmallInteger('score');
            $table->timestamps();

            $table->unique(
                ['pro_test_session_id', 'pro_test_candidate_id', 'game_number'],
                'pro_test_scores_v2_session_candidate_game_unique'
            );
        });

        Schema::create('pro_test_result_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_test_session_id')->constrained('pro_test_sessions')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedInteger('row_count')->default(0);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(
                ['pro_test_session_id', 'revision'],
                'pro_test_result_publications_session_revision_unique'
            );
        });

        Schema::create('pro_test_result_publication_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_test_result_publication_id')
                ->constrained('pro_test_result_publications')
                ->cascadeOnDelete();
            $table->foreignId('pro_test_candidate_id')->nullable()->constrained('pro_test_candidates')->nullOnDelete();
            $table->unsignedInteger('rank');
            $table->string('exam_number');
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->string('resident_prefecture')->nullable();
            $table->string('handedness', 20)->nullable();
            $table->unsignedSmallInteger('games');
            $table->unsignedInteger('total_pin');
            $table->decimal('average', 7, 2);
            $table->string('result_label')->nullable();
            $table->json('session_scores')->nullable();
            $table->timestamps();

            $table->unique(
                ['pro_test_result_publication_id', 'exam_number'],
                'pro_test_result_publication_rows_publication_exam_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_result_publication_rows');
        Schema::dropIfExists('pro_test_result_publications');
        Schema::dropIfExists('pro_test_scores_v2');
        Schema::dropIfExists('pro_test_candidates');
        Schema::dropIfExists('pro_test_sessions');
        Schema::dropIfExists('pro_test_events');
    }
};
