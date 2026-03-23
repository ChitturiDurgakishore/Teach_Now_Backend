<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_seeker_educations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_seeker_id');

            $table->string('degree')->nullable();
            $table->string('institution')->nullable();
            $table->string('field_of_study')->nullable();
            $table->year('start_year')->nullable();
            $table->year('end_year')->nullable();
            $table->string('grade')->nullable();
            $table->boolean('is_current')->default(false);

            $table->timestamps();

            $table->foreign('job_seeker_id')
                ->references('id')
                ->on('job_seekers')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_seeker_educations');
    }
};
