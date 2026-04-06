<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            $table->string('member_class', 32)
                ->default('player')
                ->after('membership_type')
                ->comment('player / pro_instructor / honorary_or_overseas / other');

            $table->boolean('can_enter_official_tournament')
                ->default(true)
                ->after('member_class')
                ->comment('公式戦出場可否');
        });

        DB::statement(<<<'SQL'
UPDATE pro_bowlers
SET member_class = CASE
    WHEN membership_type IN ('プロインストラクター', '認定プロインストラクター') THEN 'pro_instructor'
    WHEN upper(license_no) LIKE 'T%' THEN 'pro_instructor'
    WHEN membership_type IN ('その他', '海外') THEN 'honorary_or_overseas'
    WHEN membership_type IS NULL OR btrim(membership_type) = '' THEN 'other'
    ELSE 'player'
END
SQL);

        DB::statement(<<<'SQL'
UPDATE pro_bowlers
SET can_enter_official_tournament = CASE
    WHEN member_class = 'player' AND coalesce(is_active, false) = true THEN true
    ELSE false
END
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE pro_bowlers
ADD CONSTRAINT pro_bowlers_member_class_check
CHECK (member_class IN ('player', 'pro_instructor', 'honorary_or_overseas', 'other'))
SQL);

        Schema::table('pro_bowlers', function (Blueprint $table) {
            $table->index('member_class', 'pro_bowlers_member_class_idx');
            $table->index('can_enter_official_tournament', 'pro_bowlers_can_enter_official_tournament_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            $table->dropIndex('pro_bowlers_member_class_idx');
            $table->dropIndex('pro_bowlers_can_enter_official_tournament_idx');
        });

        DB::statement('ALTER TABLE pro_bowlers DROP CONSTRAINT IF EXISTS pro_bowlers_member_class_check');

        Schema::table('pro_bowlers', function (Blueprint $table) {
            $table->dropColumn(['member_class', 'can_enter_official_tournament']);
        });
    }
};
