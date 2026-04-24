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
        Schema::table('jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('job_subscription_id')->nullable()->after('employer_id');
            $table->unsignedBigInteger('feature_subscription_id')->nullable()->after('job_subscription_id');
        });
    }

    public function down()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn(['job_subscription_id', 'feature_subscription_id']);
        });
    }
};
