<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A board session is one browser tab on a classroom board. A teacher phone
     * pairs with it through a short code and then drives one lesson on it.
     */
    public function up(): void
    {
        Schema::create('board_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('name')->nullable();
            $table->string('pair_code', 6)->nullable()->unique();
            $table->timestamp('pair_code_expires_at')->nullable();
            $table->foreignId('controller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('answer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('screen')->default('pairing');
            $table->json('options')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_sessions');
    }
};
