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
        Schema::create('navigation_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable(); // Order is naturally after 'id'
            $table->string('title');
            $table->string('url');
            $table->integer('display_order')->default(0);
            $table->boolean('show_in_nav')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('slug')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->timestamps();

            // Optional: If you want to enforce the parent relationship
            $table->foreign('parent_id')->references('id')->on('navigation_links')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_links');
    }
};
