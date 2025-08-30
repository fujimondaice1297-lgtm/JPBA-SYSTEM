<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id(); // 主キー

            // 基本情報
            $table->string('name')->comment('大会名');
            $table->date('start_date')->nullable('開始日');
            $table->date('end_date')->nullable('終了日');

            // 会場
            $table->string('venue_name')->nullable()->comment('ボウリング場名');
            $table->string('venue_address')->nullable()->comment('住所');
            $table->string('venue_tel')->nullable()->comment('電話番号');
            $table->string('venue_fax')->nullable()->comment('FAX番号');

            // 関係団体
            $table->string('host')->nullable()->comment('主催');
            $table->string('special_sponsor')->nullable()->comment('特別協賛');
            $table->string('support')->nullable()->comment('後援');
            $table->string('sponsor')->nullable()->comment('協賛');
            $table->string('supervisor')->nullable()->comment('主管');
            $table->string('authorized_by')->nullable()->comment('公認');

            // メディア
            $table->string('broadcast')->nullable()->comment('放送');
            $table->string('streaming')->nullable()->comment('配信');

            // その他
            $table->string('prize')->nullable()->comment('賞金');
            $table->string('audience')->nullable()->comment('観戦');
            $table->text('entry_conditions')->nullable()->comment('出場条件');
            $table->text('materials')->nullable()->comment('資料');
            $table->string('previous_event')->nullable()->comment('前年大会');

            // ポスター画像
            $table->string('image_path')->nullable()->comment('大会画像パス');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
