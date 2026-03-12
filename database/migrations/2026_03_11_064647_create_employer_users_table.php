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
        Schema::create('employer_users', function (Blueprint $table) {

            $table->id();

            $table->foreignId('employer_id')
                ->constrained('employers')
                ->cascadeOnDelete();

            $table->string('name',150);

            $table->string('email',150)->unique();

            $table->string('password');

            // recruiter account active or disabled
            $table->boolean('is_active')->default(true);

            $table->rememberToken();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employer_users');
    }
};
