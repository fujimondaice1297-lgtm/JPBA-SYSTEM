<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pro_test', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('受験者氏名');

            $table->foreignId('sex_id');
            $table->foreignId('area_id');
            $table->foreignId('license_id');
            $table->foreignId('place_id');
            $table->foreignId('record_type_id');
            $table->foreignId('kaiin_status_id');
            $table->foreignId('test_category_id');
            $table->foreignId('test_venue_id');
            $table->foreignId('test_result_status_id');

            $table->text('remarks')->nullable()->comment('備考');
            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_test');
    }
};
