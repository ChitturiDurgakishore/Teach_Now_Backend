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
        Schema::create('teaching_resources', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            // Files
            $table->string('pdf')->nullable();
            $table->string('resource_photo')->nullable();

            // Author
            $table->string('author_name')->nullable();
            $table->string('author_photo')->nullable();

            // Details
            $table->integer('total_pages')->nullable();
            $table->enum('answer_include', ['included', 'not_included'])->default('not_included');
            $table->integer('read_time')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();

            // Flags
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_resources');
    }
};
