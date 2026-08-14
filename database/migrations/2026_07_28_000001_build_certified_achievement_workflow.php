<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_series_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('stage');
            $table->string('shift')->nullable();
            $table->char('gender', 1)->nullable();
            $table->string('label');
            $table->unsignedSmallInteger('start_game');
            $table->unsignedSmallInteger('end_game');
            $table->boolean('is_800_eligible')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index(['tournament_id', 'stage', 'is_enabled'], 'score_series_stage_enabled_idx');
        });

        DB::statement(
            'ALTER TABLE score_series_definitions
             ADD CONSTRAINT score_series_exactly_three_games_check
             CHECK (end_game >= start_game AND end_game - start_game + 1 = 3)'
        );

        Schema::create('record_certification_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('record_type');
            $table->char('gender', 1);
            $table->unsignedBigInteger('next_number');
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['record_type', 'gender'], 'record_cert_sequence_type_gender_unique');
        });

        $now = now();
        DB::table('record_certification_sequences')->insert(
            collect(['perfect', 'eight_hundred', 'seven_ten'])
                ->crossJoin(['M', 'F'])
                ->map(fn (array $pair) => [
                    'record_type' => $pair[0],
                    'gender' => $pair[1],
                    'next_number' => 1,
                    'prefix' => null,
                    'suffix' => null,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );

        Schema::create('official_profile_stat_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $table->string('license_no')->index();
            $table->text('source_url');
            $table->timestamp('captured_at')->index();
            $table->unsignedInteger('perfect_count')->default(0);
            $table->unsignedInteger('eight_hundred_count')->default(0);
            $table->unsignedInteger('seven_ten_count')->default(0);
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->timestamps();

            $table->unique(
                ['pro_bowler_id', 'payload_hash'],
                'official_profile_snapshot_bowler_hash_unique'
            );
        });

        Schema::table('record_types', function (Blueprint $table): void {
            $table->foreignId('tournament_id')
                ->nullable()
                ->constrained('tournaments')
                ->nullOnDelete();
            $table->foreignId('source_game_score_id')
                ->nullable()
                ->constrained('game_scores')
                ->nullOnDelete();
            $table->foreignId('score_series_definition_id')
                ->nullable()
                ->constrained('score_series_definitions')
                ->nullOnDelete();
            $table->string('stage')->nullable();
            $table->string('shift')->nullable();
            $table->char('gender', 1)->nullable();
            $table->string('series_label')->nullable();
            $table->unsignedSmallInteger('series_start_game')->nullable();
            $table->unsignedSmallInteger('series_end_game')->nullable();
            $table->unsignedSmallInteger('series_total')->nullable();
            $table->json('series_scores')->nullable();
            $table->string('status')->default('confirmed')->index();
            $table->string('registration_mode')->default('historical_backfill')->index();
            $table->string('detection_key')->nullable()->unique();
            $table->string('source_type')->nullable()->index();
            $table->text('source_url')->nullable();
            $table->string('source_label')->nullable();
            $table->text('evidence_text')->nullable();
            $table->text('warning')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('count_applied_at')->nullable();
            $table->unsignedBigInteger('certification_number_value')->nullable();
            $table->text('notes')->nullable();

            $table->index(
                ['pro_bowler_id', 'record_type', 'status'],
                'record_types_bowler_type_status_idx'
            );
            $table->unique(
                ['record_type', 'gender', 'certification_number_value'],
                'record_types_certification_number_unique'
            );
        });

        Schema::table('record_types', function (Blueprint $table): void {
            $table->string('game_numbers')->nullable()->change();
            $table->date('awarded_on')->nullable()->change();
            $table->string('certification_number')->nullable()->change();
        });

        DB::statement(
            "ALTER TABLE record_types
             ADD CONSTRAINT record_types_status_check
             CHECK (status IN ('candidate', 'confirmed', 'rejected', 'void'))"
        );
        DB::statement(
            "ALTER TABLE record_types
             ADD CONSTRAINT record_types_registration_mode_check
             CHECK (registration_mode IN ('historical_backfill', 'new_achievement'))"
        );
        DB::statement(
            "ALTER TABLE record_certification_sequences
             ADD CONSTRAINT record_cert_sequences_type_check
             CHECK (record_type IN ('perfect', 'seven_ten', 'eight_hundred'))"
        );
        DB::statement(
            "ALTER TABLE record_certification_sequences
             ADD CONSTRAINT record_cert_sequences_gender_check
             CHECK (gender IN ('M', 'F'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE record_types DROP CONSTRAINT IF EXISTS record_types_status_check');
        DB::statement('ALTER TABLE record_types DROP CONSTRAINT IF EXISTS record_types_registration_mode_check');

        Schema::table('record_types', function (Blueprint $table): void {
            $table->dropUnique('record_types_certification_number_unique');
            $table->dropIndex('record_types_bowler_type_status_idx');
            $table->dropUnique(['detection_key']);
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropConstrainedForeignId('score_series_definition_id');
            $table->dropConstrainedForeignId('source_game_score_id');
            $table->dropConstrainedForeignId('tournament_id');
            $table->dropColumn([
                'stage',
                'shift',
                'gender',
                'series_label',
                'series_start_game',
                'series_end_game',
                'series_total',
                'series_scores',
                'status',
                'registration_mode',
                'detection_key',
                'source_type',
                'source_url',
                'source_label',
                'evidence_text',
                'warning',
                'detected_at',
                'confirmed_at',
                'count_applied_at',
                'certification_number_value',
                'notes',
            ]);
        });

        Schema::table('record_types', function (Blueprint $table): void {
            $table->string('game_numbers')->nullable(false)->change();
            $table->date('awarded_on')->nullable(false)->change();
            $table->string('certification_number')->nullable(false)->change();
        });

        Schema::dropIfExists('official_profile_stat_snapshots');
        Schema::dropIfExists('record_certification_sequences');
        Schema::dropIfExists('score_series_definitions');
    }
};
