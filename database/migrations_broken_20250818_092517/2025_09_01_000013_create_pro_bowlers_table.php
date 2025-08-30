<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_bowlers', function (Blueprint $table) {
            $table->id(); // 自動増分PK
            $table->string('license_no')->unique()->comment('ライセンスNo.');
            $table->text('name_kanji')->nullable()->comment('氏名（漢字）');
            $table->text('name_kana')->nullable()->comment('氏名（カナ）');
            $table->tinyInteger('sex')->default(0)->comment('性別 0=不明,1=男,2=女');
            $table->unsignedBigInteger('district_id')->nullable()->comment('地区ID');
            $table->date('acquire_date')->nullable()->comment('取得日');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->boolean('is_visible')->default(true)->comment('表示フラグ');
            $table->boolean('coach_qualification')->default(false)->comment('コーチ資格');
            $table->timestamps();
            $table->string('kibetsu')->nullable()->comment('期別');
            $table->string('membership_type')->nullable()->comment('会員種別');
            $table->date('license_issue_date')->nullable();
            $table->string('phone_home', 20)->nullable();
            $table->boolean('has_title')->default(false)->after('is_visible');
            $table->boolean('is_district_leader')->default(false)->after('has_title');
            $table->boolean('has_sports_coach_license')->default(false)->after('is_district_leader');
            $table->string('sports_coach_name')->nullable()->after('has_sports_coach_license');
            $table->date('birthdate')->nullable();
            $table->string('birthplace')->nullable();
            $table->integer('height_cm')->nullable();
            $table->integer('weight_kg')->nullable();
            $table->string('blood_type', 3)->nullable();
            $table->string('home_zip', 10)->nullable();
            $table->string('home_address')->nullable();
            $table->string('work_zip', 10)->nullable();
            $table->string('work_address')->nullable();
            $table->string('work_place')->nullable();
            $table->string('work_place_url')->nullable();
            $table->string('phone_work', 20)->nullable();
            $table->string('phone_mobile', 20)->nullable();
            $table->string('fax_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('image_path')->nullable();
            $table->string('public_image_path')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->tinyInteger('mailing_preference')->nullable();
            $table->integer('pro_entry_year')->nullable();
            $table->string('school')->nullable()->comment('出身校');
            $table->string('hobby')->nullable()->comment('趣味・特技');
            $table->string('bowling_history')->nullable()->comment('ボウリング歴');
            $table->text('other_sports_history')->nullable()->comment('他スポーツ歴');
            $table->string('season_goal')->nullable()->comment('今シーズン目標');
            $table->string('coach')->nullable()->comment('師匠・コーチ');
            $table->text('selling_point')->nullable()->comment('セールスポイント');
            $table->text('free_comment')->nullable()->comment('自由記入欄');
            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            $table->string('instagram')->nullable();
            $table->string('rankseeker')->nullable();
            $table->string('jbc_driller_cert')->nullable();
            $table->date('a_license_date')->nullable();
            $table->date('permanent_seed_date')->nullable();
            $table->date('hall_of_fame_date')->nullable();
            $table->date('birthdate_public')->nullable();
            $table->text('memo')->nullable();
            $table->string('usbc_coach')->nullable();
            $table->string('a_class_status')->nullable();
            $table->string('a_class_year')->nullable();
            $table->string('b_class_status')->nullable();
            $table->string('b_class_year')->nullable();
            $table->string('c_class_status')->nullable();
            $table->string('c_class_year')->nullable();
            $table->string('master_status')->nullable();
            $table->string('master_year')->nullable();
            $table->string('coach_4_status')->nullable();
            $table->string('coach_4_year')->nullable();
            $table->string('coach_3_status')->nullable();
            $table->string('coach_3_year')->nullable();
            $table->string('coach_1_status')->nullable();
            $table->string('coach_1_year')->nullable();
            $table->string('kenkou_status')->nullable();
            $table->string('kenkou_year')->nullable();
            $table->string('school_license_status')->nullable();
            $table->string('school_license_year')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            $table->dropColumn([
                'has_title','is_district_leader','has_sports_coach_license',
                'sports_coach_name', 'license_issue_date', 'pro_entry_year',
                'birthdate', 'birthplace', 'height_cm', 'weight_kg', 'blood_type',
                'home_zip', 'home_address', 'phone_home', 'work_zip', 'work_address',
                'work_place', 'work_place_url', 'phone_work', 'phone_mobile', 'fax_number',
                'email', 'image_path', 'public_image_path', 'qr_code_path', 'mailing_preference',
                'school', 'hobby', 'bowling_history', 'other_sports_history',
                'season_goal', 'coach', 'selling_point', 'free_comment',
                'facebook', 'twitter', 'instagram', 'rankseeker', 'jbc_driller_cert',
                'a_license_date', 'permanent_seed_date', 'hall_of_fame_date', 'birthdate_public',
                'memo', 'usbc_coach',
                'a_class_status', 'a_class_year',
                'b_class_status', 'b_class_year',
                'c_class_status', 'c_class_year',
                'master_status', 'master_year',
                'coach_4_status', 'coach_4_year',
                'coach_3_status', 'coach_3_year',
                'coach_1_status', 'coach_1_year',
                'kenkou_status', 'kenkou_year',
                'school_license_status', 'school_license_year',
            ]);
        });
    }
};

