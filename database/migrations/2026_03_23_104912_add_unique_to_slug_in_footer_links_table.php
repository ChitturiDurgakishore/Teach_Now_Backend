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
        DB::table('footer_links')->whereNull('slug')->orWhere('slug', '')->get()->each(function ($link) {
            DB::table('footer_links')->where('id', $link->id)->update([
                'slug' => Str::slug($link->title ?? 'link') . '-' . $link->id
            ]);
        });
        Schema::table('footer_links', function (Blueprint $table) {
            $table->string('slug', 191)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('footer_links', function (Blueprint $table) {
            //
        });
    }
};
