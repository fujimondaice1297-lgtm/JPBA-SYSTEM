<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_participants', 'participant_type')) {
                $table->string('participant_type', 20)->default('pro')->index();
            }

            if (!Schema::hasColumn('tournament_participants', 'display_name')) {
                $table->string('display_name')->nullable();
            }

            if (!Schema::hasColumn('tournament_participants', 'display_license_no')) {
                $table->string('display_license_no', 50)->nullable();
            }

            if (!Schema::hasColumn('tournament_participants', 'gender')) {
                $table->string('gender', 1)->nullable()->index();
            }

            if (!Schema::hasColumn('tournament_participants', 'shift')) {
                $table->string('shift')->nullable();
            }

            if (!Schema::hasColumn('tournament_participants', 'lane')) {
                $table->smallInteger('lane')->nullable();
            }

            if (!Schema::hasColumn('tournament_participants', 'lane_slot')) {
                $table->smallInteger('lane_slot')->nullable();
            }

            if (!Schema::hasColumn('tournament_participants', 'lane_label')) {
                $table->string('lane_label', 50)->nullable();
            }

            if (!Schema::hasColumn('tournament_participants', 'box_no')) {
                $table->smallInteger('box_no')->nullable();
            }

            if (!Schema::hasColumn('tournament_participants', 'sort_order')) {
                $table->integer('sort_order')->nullable();
            }

            if (!Schema::hasColumn('tournament_participants', 'source_note')) {
                $table->text('source_note')->nullable();
            }

            if (!Schema::hasColumn('tournament_participants', 'is_temporary')) {
                $table->boolean('is_temporary')->default(false);
            }
        });

        Schema::table('game_scores', function (Blueprint $table) {
            if (!Schema::hasColumn('game_scores', 'tournament_participant_id')) {
                $table->unsignedBigInteger('tournament_participant_id')->nullable()->index();

                $table->foreign('tournament_participant_id', 'game_scores_tournament_participant_id_foreign')
                    ->references('id')
                    ->on('tournament_participants')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_scores', function (Blueprint $table) {
            if (Schema::hasColumn('game_scores', 'tournament_participant_id')) {
                $table->dropForeign('game_scores_tournament_participant_id_foreign');
                $table->dropColumn('tournament_participant_id');
            }
        });

        Schema::table('tournament_participants', function (Blueprint $table) {
            foreach ([
                'participant_type',
                'display_name',
                'display_license_no',
                'gender',
                'shift',
                'lane',
                'lane_slot',
                'lane_label',
                'box_no',
                'sort_order',
                'source_note',
                'is_temporary',
            ] as $column) {
                if (Schema::hasColumn('tournament_participants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
