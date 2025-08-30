<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('instructors', function (Blueprint $table) {
        // まず外部キーをドロップ（呪文）
        $table->dropForeign(['district_id']);

        // 型変更（爆破後の建て直し）
        $table->string('district_id')->change();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

