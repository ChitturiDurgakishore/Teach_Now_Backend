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
        Schema::create('bookmarked_jobs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('job_seeker_id')->constrained()->cascadeOnDelete();

            $table->foreignId('job_id')->constrained()->cascadeOnDelete();

            $table->string('slug')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmarked_jobs');
    }
};
