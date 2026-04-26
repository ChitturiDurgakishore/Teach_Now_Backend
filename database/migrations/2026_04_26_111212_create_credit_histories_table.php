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
        Schema::create('credit_histories', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('job_id')->nullable();
            $table->unsignedBigInteger('employer_id');
            $table->unsignedBigInteger('recruiter_id')->nullable();
            $table->unsignedBigInteger('subscription_id');

            // 🔥 important
            $table->enum('type', ['job', 'feature', 'republish']);

            $table->timestamps();

            // optional indexes (recommended)
            $table->index(['employer_id']);
            $table->index(['subscription_id']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_histories');
    }
};
