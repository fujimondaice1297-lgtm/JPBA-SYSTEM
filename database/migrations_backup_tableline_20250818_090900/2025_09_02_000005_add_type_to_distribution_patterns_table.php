<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_patterns', function (Blueprint $table) {
            $table->string('type')->default('未設定'); 
        });
    }

    public function down()
    {
        Schema::table('distribution_patterns', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

};
