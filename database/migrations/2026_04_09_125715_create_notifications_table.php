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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // 🔥 polymorphic (clean)
            $table->string('notifiable_type'); // job_seeker / employer / recruiter
            $table->unsignedBigInteger('notifiable_id');

            $table->string('type'); // job_applied, job_status_updated, etc.
            $table->string('title');
            $table->text('message')->nullable();

            $table->json('data')->nullable();

            $table->boolean('is_read')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
