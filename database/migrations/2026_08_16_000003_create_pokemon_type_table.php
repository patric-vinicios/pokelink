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
        Schema::create('pokemon_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('pokemon_number');
            $table->foreignId('type_id')->constrained('types')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('pokemon_number')->references('number')->on('pokemon')->cascadeOnDelete();

            // Upsert conflict target — guarantees at most one row per Pokémon/type
            // pair regardless of how many times the sync job re-runs. No `slot`
            // (primary/secondary order) column: that order is only available on
            // the per-Pokémon detail endpoint, which the sync job's 19-call
            // budget deliberately excludes.
            $table->unique(['pokemon_number', 'type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pokemon_type');
    }
};
