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
        Schema::create('job_seeker_cvs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_seeker_id')->constrained()->cascadeOnDelete();

            $table->string('title')->nullable(); // e.g. "Backend Developer CV"
            $table->longText('content')->nullable(); // AI generated JSON / HTML

            $table->string('pdf_path')->nullable(); // stored PDF
            $table->boolean('is_default')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_seeker_c_v_s');
    }
};
