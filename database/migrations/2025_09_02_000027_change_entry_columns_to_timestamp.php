<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->timestamp('entry_start')->nullable()->change();
            $table->timestamp('entry_end')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->date('entry_start')->nullable()->change();
            $table->date('entry_end')->nullable()->change();
        });
    }

};
