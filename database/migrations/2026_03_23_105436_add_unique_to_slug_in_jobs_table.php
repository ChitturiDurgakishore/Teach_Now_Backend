<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('jobs')->whereNull('slug')->orWhere('slug', '')->update([
            'slug' => DB::raw("CONCAT('job-', id)")
        ]);

        // Step B: Apply the unique constraint
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('slug', 191)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            //
        });
    }
};
