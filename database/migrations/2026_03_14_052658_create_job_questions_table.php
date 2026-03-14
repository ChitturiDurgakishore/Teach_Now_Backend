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
        Schema::create('job_questions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('job_id')->constrained()->cascadeOnDelete();

            $table->enum('question_type', [
                'mcq',
                'boolean',
                'numeric',
                'text'
            ]);

            $table->text('question');

            $table->string('recruiter_answer')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_questions');
    }
};
