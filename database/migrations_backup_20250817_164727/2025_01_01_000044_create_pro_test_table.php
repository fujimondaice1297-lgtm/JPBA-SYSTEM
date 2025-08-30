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

            $table->foreignId('sex_id')->constrained('sexes');
            $table->foreignId('area_id')->constrained('area');
            $table->foreignId('license_id')->constrained('license');
            $table->foreignId('place_id')->constrained('place');
            $table->foreignId('record_type_id')->constrained('record_type');
            $table->foreignId('kaiin_status_id')->constrained('kaiin_status');
            $table->foreignId('test_category_id')->constrained('pro_test_category');
            $table->foreignId('test_venue_id')->constrained('pro_test_venue');
            $table->foreignId('test_result_status_id')->constrained('pro_test_result_status');

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
