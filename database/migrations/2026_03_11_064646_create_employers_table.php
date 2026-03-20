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
        Schema::create('employers', function (Blueprint $table) {

            $table->id();

            $table->string('company_name', 200);
            $table->text('company_description')->nullable();

            $table->string('industry', 150)->nullable();

            $table->string('website')->nullable();
            $table->string('role')->default('employer');
            $table->string('company_logo')->nullable();

            $table->text('address')->nullable();

            $table->string('email', 150)->unique()->nullable(false);
            $table->string('phone', 20)->nullable();

            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();

            $table->string('map_link')->nullable();

            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('slug')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('password');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employers');
    }
};
