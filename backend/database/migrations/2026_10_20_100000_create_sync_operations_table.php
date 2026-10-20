<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every grade or skip the phone makes carries a client_uuid. The result is
     * kept here, so a request that is sent again (lost response, offline queue)
     * is answered with the first result instead of being applied twice.
     */
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->string('type', 20);
            $table->foreignId('answer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->string('error_code', 50)->nullable();
            $table->string('message')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
