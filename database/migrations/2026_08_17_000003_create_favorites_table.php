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
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('pokemon_number');
            $table->timestamps();

            $table->foreign('pokemon_number')->references('number')->on('pokemon')->cascadeOnDelete();

            // Idempotency backstop behind firstOrCreate — a double-click or
            // duplicated request can never insert a second row.
            $table->unique(['user_id', 'pokemon_number']);
            // Every read is scoped by user_id; created_at covers the default
            // "most recently favorited" ordering and the nav badge's count().
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
