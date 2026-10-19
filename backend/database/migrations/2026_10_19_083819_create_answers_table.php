<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per question asked to a student in a lesson. This table is the
     * source of truth for the random queue rules R1-R8.
     */
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('score')->nullable();
            $table->char('chosen_option', 1)->nullable();
            $table->text('comment')->nullable();
            $table->unsignedTinyInteger('pick_weight')->default(1);
            $table->string('pick_reason', 32)->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['lesson_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
