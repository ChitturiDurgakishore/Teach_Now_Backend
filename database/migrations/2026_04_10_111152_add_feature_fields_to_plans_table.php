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
        Schema::table('plans', function (Blueprint $table) {

            // 🔥 Featured jobs limit
            $table->integer('featured_jobs_limit')->default(0)->after('job_live_days');

            // 🔥 Can feature company or not
            $table->boolean('company_featured')->default(false)->after('featured_jobs_limit');
        });
    }

    public function down()
    {
        Schema::table('plans', function (Blueprint $table) {

            $table->dropColumn(['featured_jobs_limit', 'company_featured']);
        });
    }
};
