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
        Schema::create('job_applications', function (Blueprint $table) {

            $table->id();

            $table->foreignId('job_id')
                ->constrained('jobs')
                ->cascadeOnDelete();

            $table->foreignId('job_seeker_id')
                ->constrained('job_seekers')
                ->cascadeOnDelete();

            $table->foreignId('resume_id')
                ->constrained('resumes')
                ->cascadeOnDelete();

            $table->text('cover_letter')->nullable();

            $table->enum('status', [
                'applied',
                'shortlisted',
                'rejected'
            ])->default('applied');

            $table->string('slug')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
