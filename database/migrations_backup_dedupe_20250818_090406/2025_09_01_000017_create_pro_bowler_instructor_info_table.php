<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProBowlerInstructorInfoTable extends Migration
{
    public function up(): void
    {
        Schema::create('pro_bowler_instructor_info', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pro_bowler_id')->index(); // 外部キー参照カラム
            $table->boolean('instructor_flag')->default(false);
            $table->string('lesson_center')->nullable();
            $table->text('lesson_notes')->nullable();
            $table->text('certifications')->nullable()->comment('資格など（例: 公認アシスタントマネージャー）');
            $table->timestamps();

            $table->foreignId('pro_bowler_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_bowler_instructor_info');
    }
}

