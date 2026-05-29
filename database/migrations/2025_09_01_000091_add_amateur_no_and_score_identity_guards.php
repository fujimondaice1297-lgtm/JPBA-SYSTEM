<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amateur_bowlers') && !Schema::hasColumn('amateur_bowlers', 'amateur_no')) {
            Schema::table('amateur_bowlers', function (Blueprint $table) {
                $table->string('amateur_no', 32)->nullable();
            });
        }

        if (Schema::hasTable('amateur_bowlers') && Schema::hasColumn('amateur_bowlers', 'amateur_no')) {
            $rows = DB::table('amateur_bowlers')
                ->select(['id', 'amateur_no'])
                ->orderBy('id')
                ->get();

            $usedNumbers = [];
            foreach ($rows as $row) {
                $normalized = $this->normalizeAmateurNo((string) ($row->amateur_no ?? ''));
                if ($normalized !== '') {
                    $usedNumbers[$normalized] = true;
                }
            }

            $nextNumber = 1;
            foreach ($rows as $row) {
                $normalized = $this->normalizeAmateurNo((string) ($row->amateur_no ?? ''));

                if ($normalized === '') {
                    do {
                        $normalized = 'A' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
                        $nextNumber++;
                    } while (isset($usedNumbers[$normalized]));

                    $usedNumbers[$normalized] = true;
                }

                DB::table('amateur_bowlers')
                    ->where('id', $row->id)
                    ->update([
                        'amateur_no' => $normalized,
                        'updated_at' => now(),
                    ]);
            }

            DB::statement('ALTER TABLE amateur_bowlers ALTER COLUMN amateur_no SET NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS amateur_bowlers_amateur_no_unique ON amateur_bowlers (amateur_no)');
        }

        if (
            Schema::hasTable('game_scores')
            && Schema::hasColumn('game_scores', 'tournament_id')
            && Schema::hasColumn('game_scores', 'stage')
            && Schema::hasColumn('game_scores', 'shift')
            && Schema::hasColumn('game_scores', 'game_number')
            && Schema::hasColumn('game_scores', 'tournament_participant_id')
        ) {
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS game_scores_participant_game_unique
                ON game_scores (
                    tournament_id,
                    stage,
                    COALESCE(shift, ''),
                    game_number,
                    tournament_participant_id
                )
                WHERE tournament_participant_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS game_scores_participant_game_unique');
        DB::statement('DROP INDEX IF EXISTS amateur_bowlers_amateur_no_unique');

        if (Schema::hasTable('amateur_bowlers') && Schema::hasColumn('amateur_bowlers', 'amateur_no')) {
            Schema::table('amateur_bowlers', function (Blueprint $table) {
                $table->dropColumn('amateur_no');
            });
        }
    }

    private function normalizeAmateurNo(string $value): string
    {
        $value = mb_convert_kana(trim($value), 'as', 'UTF-8');
        $value = strtoupper(str_replace([' ', '　', '-', '_'], '', $value));

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return 'A' . str_pad($value, 6, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^A(\d+)$/', $value, $matches) === 1) {
            return 'A' . str_pad($matches[1], 6, '0', STR_PAD_LEFT);
        }

        return $value;
    }
};
