<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('instructors', function (Blueprint $table) {
            $table->string('license_no')->primary();
            $table->unsignedBigInteger('pro_bowler_id')->nullable();
            $table->string('name');
            $table->string('name_kana')->nullable();
            $table->boolean('sex');
            $table->unsignedBigInteger('district_id')->nullable();
            $table->enum('instructor_type', ['pro', 'certified']);
            $table->string('grade');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->boolean('coach_qualification')->default(false);
            $table->timestamps();

            $table->foreign('pro_bowler_id')->references('id')->on('pro_bowlers')->onDelete('set null');
            $table->foreign('district_id')->references('id')->on('districts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructors');
    }
};
