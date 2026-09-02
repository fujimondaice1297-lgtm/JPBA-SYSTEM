<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_test_candidates', function (Blueprint $table): void {
            $table->string('entry_stage')->default('first')->index();
            $table->string('entry_reason')->default('regular')->index();
            $table->foreignId('previous_candidate_id')
                ->nullable()
                ->unique()
                ->constrained('pro_test_candidates')
                ->nullOnDelete();
            $table->timestamp('exemption_approved_at')->nullable();
            $table->foreignId('exemption_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('exemption_note')->nullable();
        });

        Schema::create('pro_test_candidate_stage_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_test_candidate_id')->constrained('pro_test_candidates')->cascadeOnDelete();
            $table->string('stage_code');
            $table->string('result')->default('pending')->index();
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['pro_test_candidate_id', 'stage_code'],
                'pro_test_candidate_stage_results_candidate_stage_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_candidate_stage_results');

        Schema::table('pro_test_candidates', function (Blueprint $table): void {
            $table->dropForeign(['previous_candidate_id']);
            $table->dropForeign(['exemption_approved_by']);
            $table->dropIndex(['entry_stage']);
            $table->dropIndex(['entry_reason']);
            $table->dropColumn([
                'entry_stage',
                'entry_reason',
                'previous_candidate_id',
                'exemption_approved_at',
                'exemption_approved_by',
                'exemption_note',
            ]);
        });
    }
};
