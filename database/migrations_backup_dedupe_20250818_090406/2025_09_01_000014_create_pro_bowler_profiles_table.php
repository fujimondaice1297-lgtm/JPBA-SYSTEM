<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_bowler_profiles', function (Blueprint $table) {
            $table->id();

            // ← これ“だけ”でOK（FKはPhase Bで後付け）
            $table->foreignId('pro_bowler_id'); 

            $table->date('birthdate')->nullable();
            $table->string('birthplace')->nullable();
            $table->integer('height_cm')->nullable();
            $table->integer('weight_kg')->nullable();
            $table->string('blood_type')->nullable();

            $table->string('home_zip')->nullable();
            $table->string('home_address')->nullable();
            $table->string('phone_home')->nullable();

            $table->string('work_zip')->nullable();
            $table->string('work_address')->nullable();
            $table->string('work_place')->nullable();
            $table->string('phone_work')->nullable();
            $table->string('work_place_url')->nullable()->comment('勤務先URL');

            $table->string('phone_mobile')->nullable();
            $table->string('fax_number')->nullable();
            $table->string('email')->nullable();

            $table->string('image_path')->nullable()->comment('非公開用プロフィール画像');
            $table->string('public_image_path')->nullable()->comment('公開用プロフィール画像');
            $table->string('qr_code_path')->nullable()->comment('QRコード画像パス');

            $table->tinyInteger('mailing_preference')->nullable()->comment('郵送区分: 1=自宅, 2=勤務先');
            $table->date('license_issue_date')->nullable()->comment('ライセンス交付日');
            $table->integer('pro_entry_year')->nullable()->comment('プロ入り年');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_profiles');
    }
};
