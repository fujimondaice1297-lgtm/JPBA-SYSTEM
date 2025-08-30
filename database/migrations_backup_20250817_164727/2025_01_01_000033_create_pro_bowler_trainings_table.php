<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_bowler_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $table->foreignId('training_id')->constrained('trainings');
            $table->date('completed_at');
            $table->date('expires_at'); // 期限は保存時に計算して入れる
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['pro_bowler_id','training_id','expires_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_trainings');
    }

};
