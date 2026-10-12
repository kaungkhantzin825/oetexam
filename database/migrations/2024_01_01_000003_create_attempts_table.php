<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guarded — no-op when the table already exists (the normal case here). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attempts')) {
            return;
        }

        Schema::create('attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('exam_id');
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->decimal('total_score', 6, 2)->default(0);
            $table->decimal('max_score', 6, 2)->default(0);
            $table->decimal('band_score', 3, 1)->default(0)->comment('OET 0.0 - 6.0 scale');
            $table->json('section_scores')->nullable()->comment('{"Listening":4.5,"Reading":5.0,...}');
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id', 'idx_attempts_user');
            $table->index('exam_id', 'idx_attempts_exam');
            $table->index('status', 'idx_attempts_status');
            $table->foreign('user_id', 'fk_attempts_user')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('exam_id', 'fk_attempts_exam')
                ->references('id')->on('exams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
    }
};
