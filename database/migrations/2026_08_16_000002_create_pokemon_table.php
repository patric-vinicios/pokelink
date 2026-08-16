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
        Schema::create('pokemon', function (Blueprint $table) {
            // Primary key is the national dex number, not an auto-increment id —
            // it is both a natural identifier and the sync job's upsert target.
            $table->unsignedSmallInteger('number')->primary();
            $table->string('name', 64);
            $table->string('slug', 64)->unique();
            $table->string('sprite_url', 255);
            $table->timestamps();

            // Backs F07's `LIKE '%term%'` search.
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pokemon');
    }
};
