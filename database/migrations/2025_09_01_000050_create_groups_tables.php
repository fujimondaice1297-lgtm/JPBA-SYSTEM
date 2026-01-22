<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();                 // 例: gender-m, district-leader, tournament-2025-3
            $t->string('name');                          // 表示名
            $t->enum('type', ['rule','snapshot']);       // ルール or スナップショット
            $t->json('rule_json')->nullable();           // ルール定義(JSON)
            $t->enum('retention', ['forever','fye','until']) // 保持: 永続/年度末/日付指定
              ->default('forever');
            $t->date('expires_at')->nullable();          // retention=until のときだけ使用
            $t->boolean('show_on_mypage')->default(false); // マイページに案内を出すか
            $t->timestamps();
        });

        Schema::create('group_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $t->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $t->enum('source', ['rule','manual','snapshot'])->default('rule');
            $t->timestamp('assigned_at')->nullable();
            $t->date('expires_at')->nullable();          // 期限（年度末や日付指定）
            $t->unique(['group_id','pro_bowler_id']);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
