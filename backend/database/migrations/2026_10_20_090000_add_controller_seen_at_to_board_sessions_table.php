<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * last_seen_at is touched by the board itself; controller_seen_at is the
     * last heartbeat from the paired phone, so the board can tell the class
     * that the remote dropped off (D9 «Pult uzildi»).
     */
    public function up(): void
    {
        Schema::table('board_sessions', function (Blueprint $table) {
            $table->timestamp('controller_seen_at')->nullable()->after('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_sessions', function (Blueprint $table) {
            $table->dropColumn('controller_seen_at');
        });
    }
};
