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
        Schema::create('jobs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('employer_id')->constrained()->cascadeOnDelete();

            $table->foreignId('created_by')->constrained('employer_users');

            $table->foreignId('category_id')->constrained();

            $table->string('title');

            $table->text('description');

            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();

            $table->integer('vacancies')->default(1);

            $table->string('location', 200)->nullable();

            $table->integer('experience_required')->default(0);

            $table->enum('job_type', [
                'full_time',
                'part_time',
                'internship',
                'contract'
            ]);

            $table->enum('job_status', [
                'open',
                'closed',
                'filled'
            ])->default('open');

            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');

            $table->boolean('featured')->default(false);
            $table->boolean('admin_featured')->default(false);

            $table->date('application_deadline')->nullable();

            $table->string('slug')->nullable();

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
