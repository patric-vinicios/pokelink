<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // Always the lower of the two participant ids — betweenUsers()
            // normalizes the pair before firstOrCreate(), so two users
            // messaging each other never produce two conversation rows.
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_one_id', 'user_two_id']);
        });

        // DB-level backstop for the app-level pair ordering — MySQL only.
        // SQLite (test connection) only accepts CHECK constraints declared
        // inline in CREATE TABLE, not via ALTER TABLE, so this stays a
        // MySQL-only guarantee; the ordering itself is still enforced and
        // tested at the application level in Conversation::betweenUsers().
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE conversations ADD CONSTRAINT conversations_ordered_pair_check CHECK (user_one_id < user_two_id)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
