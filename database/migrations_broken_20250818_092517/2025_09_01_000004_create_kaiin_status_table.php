<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kaiin_status', function (Blueprint $table) {
            $table->id(); // 主キー
            $table->enum('name', ['一般', '学生', 'ジュニア', 'その他'])->comment('会員ステータス');
            $table->timestamp('reg_date')->nullable()->comment('登録日時');
            $table->boolean('del_flg')->default(false)->comment('削除フラグ');
            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaiin_status');
    }
};


