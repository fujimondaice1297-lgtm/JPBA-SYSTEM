<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amateur_bowlers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->string('gender', 1)->nullable();
            $table->string('dominant_arm', 20)->nullable();
            $table->string('affiliation_name')->nullable();
            $table->string('equipment_contract')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['name', 'gender']);
            $table->index('name_kana');
            $table->index('is_active');
        });

        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->foreignId('amateur_bowler_id')
                ->nullable()
                ->after('pro_bowler_id')
                ->constrained('amateur_bowlers')
                ->nullOnDelete();

            $table->string('display_dominant_arm', 20)->nullable()->after('display_license_no');
            $table->string('display_affiliation_name')->nullable()->after('display_dominant_arm');
            $table->string('display_equipment_contract')->nullable()->after('display_affiliation_name');

            $table->index('amateur_bowler_id');
        });

        $now = now();

        $existingAmateurs = DB::table('tournament_participants')
            ->where('participant_type', 'amateur')
            ->whereNull('amateur_bowler_id')
            ->whereNotNull('display_name')
            ->orderBy('id')
            ->get();

        foreach ($existingAmateurs as $participant) {
            $name = trim((string) ($participant->display_name ?? ''));
            if ($name === '') {
                continue;
            }

            $gender = trim((string) ($participant->gender ?? '')) ?: null;

            $amateurBowler = DB::table('amateur_bowlers')
                ->where('name', $name)
                ->when($gender, fn ($query) => $query->where('gender', $gender), fn ($query) => $query->whereNull('gender'))
                ->first();

            if (!$amateurBowler) {
                $amateurBowlerId = DB::table('amateur_bowlers')->insertGetId([
                    'name' => $name,
                    'name_kana' => null,
                    'gender' => $gender,
                    'dominant_arm' => null,
                    'affiliation_name' => null,
                    'equipment_contract' => null,
                    'note' => '既存 tournament_participants から初回作成',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $amateurBowlerId = (int) $amateurBowler->id;
            }

            DB::table('tournament_participants')
                ->where('id', $participant->id)
                ->update([
                    'amateur_bowler_id' => $amateurBowlerId,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->dropForeign(['amateur_bowler_id']);
            $table->dropIndex(['amateur_bowler_id']);
            $table->dropColumn([
                'amateur_bowler_id',
                'display_dominant_arm',
                'display_affiliation_name',
                'display_equipment_contract',
            ]);
        });

        Schema::dropIfExists('amateur_bowlers');
    }
};
