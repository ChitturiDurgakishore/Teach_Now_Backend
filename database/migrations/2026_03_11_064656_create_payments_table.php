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
        Schema::create('payments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('employer_id')->constrained();

            $table->foreignId('subscription_id')->constrained();

            $table->decimal('amount', 10, 2);

            $table->string('payment_method');

            $table->enum('payment_status', [
                'pending',
                'success',
                'failed'
            ]);

            $table->string('transaction_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
