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
        Schema::table('subscriptions', function (Blueprint $table) {

            // 🔥 Total allowed from plan
            $table->integer('featured_jobs_total')->default(0)->after('job_posts_used');

            // 🔥 Used count
            $table->integer('featured_jobs_used')->default(0)->after('featured_jobs_total');
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {

            $table->dropColumn(['featured_jobs_total', 'featured_jobs_used']);
        });
    }
};
