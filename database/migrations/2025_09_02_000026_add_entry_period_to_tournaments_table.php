<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('tournaments', function (Blueprint $table) {
        $table->date('entry_start')->nullable();
        $table->date('entry_end')->nullable();
    });
}

public function down()
{
    Schema::table('tournaments', function (Blueprint $table) {
        $table->dropColumn(['entry_start', 'entry_end']);
    });
}
};
