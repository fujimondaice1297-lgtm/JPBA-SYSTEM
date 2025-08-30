<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_test_schedule', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->comment('開催年');
            $table->string('schedule_name')->comment('スケジュール名（第○回など）');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('application_start')->nullable();
            $table->date('application_end')->nullable();

            $table->foreignId('venue_id')->nullable();

            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test_schedule');
    }
};

