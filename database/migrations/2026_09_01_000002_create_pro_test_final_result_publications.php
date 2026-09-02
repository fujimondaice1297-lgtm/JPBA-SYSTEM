<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_test_final_result_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_test_event_id')->constrained('pro_test_events')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedInteger('row_count')->default(0);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(
                ['pro_test_event_id', 'revision'],
                'pro_test_final_publications_event_revision_unique'
            );
        });

        Schema::create('pro_test_final_result_publication_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_test_final_result_publication_id')
                ->constrained('pro_test_final_result_publications')
                ->cascadeOnDelete();
            $table->foreignId('pro_test_candidate_id')->nullable()->constrained('pro_test_candidates')->nullOnDelete();
            $table->string('gender', 1);
            $table->string('exam_number');
            $table->string('license_no')->nullable();
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['pro_test_final_result_publication_id', 'exam_number'],
                'pro_test_final_rows_publication_exam_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_final_result_publication_rows');
        Schema::dropIfExists('pro_test_final_result_publications');
    }
};
