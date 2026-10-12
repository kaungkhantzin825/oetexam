<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guarded — no-op when the table already exists (the normal case here). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('answers')) {
            return;
        }

        Schema::create('answers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('attempt_id');
            $table->unsignedInteger('question_id');
            $table->text('user_answer')->nullable();
            $table->boolean('is_correct')->nullable()->comment('NULL = pending manual/AI marking');
            $table->decimal('score', 6, 2)->default(0);
            $table->decimal('max_score', 6, 2)->default(1);
            $table->boolean('is_marked')->default(false)->comment('Student "mark for review" flag');
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->text('ai_feedback')->nullable()->comment('AI feedback JSON or text');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['attempt_id', 'question_id'], 'uq_answers_attempt_question');
            $table->index('attempt_id', 'idx_answers_attempt');
            $table->foreign('attempt_id', 'fk_answers_attempt')
                ->references('id')->on('attempts')->onDelete('cascade');
            $table->foreign('question_id', 'fk_answers_question')
                ->references('id')->on('questions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
    }
};
