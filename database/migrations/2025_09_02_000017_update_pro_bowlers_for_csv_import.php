<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = 'pro_bowlers';

        // ---------- 安全なリネーム/削除（存在する時だけ） ----------
        // work_place は廃止
        DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS work_place");
        // work_place_url -> organization_url （Doctrineなしでも動く書き方）
        DB::statement(<<<'SQL'
DO $$
BEGIN
 IF EXISTS(
   SELECT 1 FROM information_schema.columns
   WHERE table_name='pro_bowlers' AND column_name='work_place_url'
 ) AND NOT EXISTS(
   SELECT 1 FROM information_schema.columns
   WHERE table_name='pro_bowlers' AND column_name='organization_url'
 )
 THEN
   ALTER TABLE pro_bowlers RENAME COLUMN work_place_url TO organization_url;
 END IF;
END $$;
SQL);

        // 出身校は使わない
        DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS school");

        // ---------- 型変換（USINGで掃除してから変える） ----------
        if (Schema::hasColumn($table, 'kibetsu')) {
            DB::statement("
                ALTER TABLE {$table}
                ALTER COLUMN kibetsu
                TYPE SMALLINT
                USING NULLIF(regexp_replace(kibetsu::text, '[^0-9]', '', 'g'), '')::smallint
            ");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN kibetsu DROP NOT NULL");
        }

        if (Schema::hasColumn($table, 'pro_entry_year')) {
            DB::statement("
                ALTER TABLE {$table}
                ALTER COLUMN pro_entry_year
                TYPE SMALLINT
                USING NULLIF(regexp_replace(pro_entry_year::text, '[^0-9]', '', 'g'), '')::smallint
            ");
        }

        if (Schema::hasColumn($table, 'mailing_preference')) {
            DB::statement("
                UPDATE {$table}
                   SET mailing_preference = CASE
                       WHEN mailing_preference::text IN ('1','自宅') THEN 1
                       WHEN mailing_preference::text IN ('2','勤務先') THEN 2
                       ELSE NULL
                   END
            ");
            DB::statement("
                ALTER TABLE {$table}
                ALTER COLUMN mailing_preference
                TYPE SMALLINT
                USING mailing_preference::smallint
            ");
        }

        // ---------- 追加カラムを一括で（IF NOT EXISTSで重複回避） ----------
        $columns = [
            // パス類
            'qr_code_path'                 => 'varchar(255)',
            'public_image_path'            => 'varchar(255)',

            // 所属先（公開側）
            'organization_name'            => 'varchar(255)',
            'organization_zip'             => 'varchar(10)',
            'organization_addr1'           => 'varchar(255)',
            'organization_addr2'           => 'varchar(255)',
            'organization_url'             => 'varchar(255)',

            // 公開住所
            'public_zip'                   => 'varchar(10)',
            'public_addr1'                 => 'varchar(255)',
            'public_addr2'                 => 'varchar(255)',
            'public_addr_same_as_org'      => 'boolean DEFAULT FALSE',

            // 送付先住所（非公開）
            'mailing_zip'                  => 'varchar(10)',
            'mailing_addr1'                => 'varchar(255)',
            'mailing_addr2'                => 'varchar(255)',
            'mailing_addr_same_as_org'     => 'boolean DEFAULT FALSE',

            // 認証・状態
            'password_change_status'       => 'smallint DEFAULT 2',  // 0:更新済 1:確認中 2:未更新
            'login_id'                     => 'varchar(255)',
            'mypage_temp_password'         => 'varchar(255)',

            // 体情報 + 公開フラグ
            'height_cm'                    => 'smallint',
            'height_is_public'             => 'boolean DEFAULT FALSE',
            'weight_kg'                    => 'smallint',
            'weight_is_public'             => 'boolean DEFAULT FALSE',
            'blood_type'                   => 'varchar(3)',
            'blood_type_is_public'         => 'boolean DEFAULT FALSE',
            'dominant_arm'                 => 'varchar(5)',

            // 資格・年度
            'usbc_coach'                   => 'varchar(20)',
            'jbc_driller_cert'             => 'varchar(2)',  // '有' / '無'
            'a_class_status'               => 'varchar(2)',
            'a_class_year'                 => 'smallint',
            'b_class_status'               => 'varchar(2)',
            'b_class_year'                 => 'smallint',
            'c_class_status'               => 'varchar(2)',
            'c_class_year'                 => 'smallint',
            'master_status'                => 'varchar(2)',
            'master_year'                  => 'smallint',
            'coach_4_status'               => 'varchar(2)',
            'coach_4_year'                 => 'smallint',
            'coach_3_status'               => 'varchar(2)',
            'coach_3_year'                 => 'smallint',
            'coach_1_status'               => 'varchar(2)',
            'coach_1_year'                 => 'smallint',
            'kenkou_status'                => 'varchar(2)',
            'kenkou_year'                  => 'smallint',
            'school_license_status'        => 'varchar(2)',
            'school_license_year'          => 'smallint',

            // 経歴・SNS・PR
            'hobby'                        => 'varchar(255)',
            'bowling_history'              => 'varchar(255)',
            'other_sports_history'         => 'text',
            'facebook'                     => 'varchar(255)',
            'twitter'                      => 'varchar(255)',
            'instagram'                    => 'varchar(255)',
            'rankseeker'                   => 'varchar(255)',
            'selling_point'                => 'text',
            'free_comment'                 => 'text',
            'motto'                        => 'varchar(255)',
            'equipment_contract'           => 'varchar(255)',
            'coaching_history'             => 'text',

            // スポンサー
            'sponsor_a'                    => 'varchar(255)',
            'sponsor_a_url'                => 'varchar(255)',
            'sponsor_b'                    => 'varchar(255)',
            'sponsor_b_url'                => 'varchar(255)',
            'sponsor_c'                    => 'varchar(255)',
            'sponsor_c_url'                => 'varchar(255)',

            // 協会役職
            'association_role'             => 'varchar(255)',

            // A級ライセンス番号（整数）
            'a_license_number'             => 'integer',
        ];

        foreach ($columns as $name => $sqlType) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS \"{$name}\" {$sqlType}");
        }
    }

    public function down(): void
    {
        // 戻さない。どうせ再構築のほうが速い。
    }
};
