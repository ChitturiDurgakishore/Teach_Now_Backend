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
        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employer_id');

            $table->string('document_name')->nullable(); // GST, License etc
            $table->string('document_file'); // file path

            $table->boolean('is_verified')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->text('admin_remark')->nullable();

            $table->timestamps();

            $table->foreign('employer_id')
                ->references('id')
                ->on('employers')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};
