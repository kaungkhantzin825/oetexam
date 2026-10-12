<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guarded — no-op when the table already exists (the normal case here). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lesson_progress')) {
            return;
        }

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('lesson_id');
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('seconds_spent')->default(0);
            $table->dateTime('last_viewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'lesson_id'], 'uq_lesson_progress');
            $table->index('user_id', 'idx_lesson_progress_user');
            $table->foreign('user_id', 'fk_lesson_progress_user')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lesson_id', 'fk_lesson_progress_lesson')
                ->references('id')->on('lessons')->onDelete('cascade');
        });
    }

    public function down(): void
    {
    }
};
