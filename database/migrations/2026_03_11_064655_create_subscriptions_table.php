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
        Schema::create('subscriptions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('employer_id')->constrained()->cascadeOnDelete();

            $table->foreignId('plan_id')->constrained('subscription_plans');

            $table->date('start_date');

            $table->date('end_date');

            $table->enum('status', [
                'active',
                'expired',
                'cancelled'
            ]);

            $table->timestamps();
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
