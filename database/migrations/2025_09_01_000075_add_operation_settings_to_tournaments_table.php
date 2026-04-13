<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tournaments')) {
            return;
        }

        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'use_shift_draw')) {
                $table->boolean('use_shift_draw')->default(false)->after('entry_end');
            }
            if (!Schema::hasColumn('tournaments', 'use_lane_draw')) {
                $table->boolean('use_lane_draw')->default(false)->after('use_shift_draw');
            }
            if (!Schema::hasColumn('tournaments', 'lane_assignment_mode')) {
                $table->string('lane_assignment_mode', 30)->default('single_lane')->after('use_lane_draw');
            }
            if (!Schema::hasColumn('tournaments', 'box_player_count')) {
                $table->unsignedSmallInteger('box_player_count')->nullable()->after('lane_assignment_mode');
            }
            if (!Schema::hasColumn('tournaments', 'odd_lane_player_count')) {
                $table->unsignedSmallInteger('odd_lane_player_count')->nullable()->after('box_player_count');
            }
            if (!Schema::hasColumn('tournaments', 'even_lane_player_count')) {
                $table->unsignedSmallInteger('even_lane_player_count')->nullable()->after('odd_lane_player_count');
            }
            if (!Schema::hasColumn('tournaments', 'accept_shift_preference')) {
                $table->boolean('accept_shift_preference')->default(false)->after('even_lane_player_count');
            }
        });

        if (Schema::hasColumn('tournaments', 'shift_codes') && Schema::hasColumn('tournaments', 'use_shift_draw')) {
            DB::table('tournaments')
                ->whereNotNull('shift_codes')
                ->where('shift_codes', '!=', '')
                ->update(['use_shift_draw' => true]);
        }

        if (
            Schema::hasColumn('tournaments', 'lane_from') &&
            Schema::hasColumn('tournaments', 'lane_to') &&
            Schema::hasColumn('tournaments', 'use_lane_draw')
        ) {
            DB::table('tournaments')
                ->whereNotNull('lane_from')
                ->whereNotNull('lane_to')
                ->update(['use_lane_draw' => true]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tournaments')) {
            return;
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $dropColumns = [];

            foreach ([
                'use_shift_draw',
                'use_lane_draw',
                'lane_assignment_mode',
                'box_player_count',
                'odd_lane_player_count',
                'even_lane_player_count',
                'accept_shift_preference',
            ] as $column) {
                if (Schema::hasColumn('tournaments', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};