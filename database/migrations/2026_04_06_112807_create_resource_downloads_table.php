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
        Schema::create('resource_downloads', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable(); // logged in user
            $table->string('resource_type')->nullable(); // resume, pdf, template etc.
            $table->unsignedBigInteger('resource_id')->nullable(); // optional

            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_downloads');
    }
};
