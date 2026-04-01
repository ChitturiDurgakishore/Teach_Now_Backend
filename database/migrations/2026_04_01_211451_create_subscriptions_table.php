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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->integer('job_posts_total');
            $table->integer('job_posts_used')->default(0);

            $table->dateTime('purchase_date');
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');

            $table->string('status')->default('active');
            // active | expired

            $table->timestamps();

            // 🔥 INDEXES (IMPORTANT FOR PERFORMANCE)
            $table->index(['employer_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
