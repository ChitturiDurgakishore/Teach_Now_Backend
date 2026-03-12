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
        Schema::create('job_seekers', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title', 150)->nullable();

            $table->string('phone', 20)->nullable();

            $table->string('location', 200)->nullable();

            $table->integer('experience_years')->default(0);

            $table->enum('availability', [
                'open',
                'not_looking'
            ])->default('open');

            $table->date('dob')->nullable();

            $table->string('portfolio_website')->nullable();

            $table->text('bio')->nullable();

            $table->string('profile_photo')->nullable();

            $table->string('slug')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_seekers');
    }
};
