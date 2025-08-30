<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sexes', function (Blueprint $table) {
            $table->id(); // intの主キー（unsignedBigInteger）
            $table->text('label')->comment('性別名（例：男性、女性）');
            $table->timestamp('update_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sexes');
    }
};
