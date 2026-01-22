<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('annual_dues', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pro_bowler_id')->constrained('pro_bowlers')->cascadeOnDelete();
            $t->unsignedSmallInteger('year');  // 西暦
            $t->date('paid_at')->nullable();   // null=未納
            $t->string('note')->nullable();
            $t->unique(['pro_bowler_id','year']);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('annual_dues'); }
};
