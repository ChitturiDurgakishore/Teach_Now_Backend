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
        Schema::table('resume_limits', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->after('id');
            $table->integer('count')->default(0);
            $table->string('month');

            $table->unique(['user_id', 'month']);
        });
    }

    public function down()
    {
        Schema::table('resume_limits', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'count', 'month']);
        });
    }
};
