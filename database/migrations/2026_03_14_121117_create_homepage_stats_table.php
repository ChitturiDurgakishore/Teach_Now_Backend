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
        Schema::create('homepage_stats', function (Blueprint $table) {
            $table->id();
            $table->integer('total_jobs')->default(0);
            $table->integer('total_companies')->default(0);
            $table->integer('total_candidates')->default(0);
            $table->integer('total_recruiters')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homepage_stats');
    }
};
