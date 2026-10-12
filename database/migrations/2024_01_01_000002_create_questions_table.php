<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guarded — no-op when the table already exists (the normal case here). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('questions')) {
            return;
        }

        Schema::create('questions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('exam_id');
            $table->enum('section', ['Listening', 'Reading', 'Writing', 'Speaking', 'Full']);
            $table->enum('question_type', ['MCQ', 'ShortAnswer', 'Writing', 'RolePlay']);
            $table->string('part', 60)->nullable()->comment('e.g. Listening Part A / Reading Part B');
            $table->unsignedInteger('order_index')->default(0);
            $table->text('question_text');
            $table->json('options')->nullable()->comment('["A option","B option",...] for MCQ');
            $table->text('correct_answer')->nullable()->comment('Index or literal answer used for auto-marking');
            $table->string('audio_url', 255)->nullable()->comment('Relative path under /uploads for Listening');
            $table->text('audio_script')->nullable()->comment('Transcript used by AI to generate questions');
            $table->text('model_answer')->nullable()->comment('Sample/model answer shown after submission');
            $table->json('role_play_card')->nullable()->comment('Speaking role-play card {role, setting, task, ...}');
            $table->unsignedInteger('marks')->default(1);
            $table->text('explanation')->nullable()->comment('Why the answer is correct');
            $table->text('explanation_my')->nullable()->comment('Myanmar translation of explanation');
            $table->text('model_answer_my')->nullable()->comment('Myanmar translation of model answer');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('exam_id', 'idx_questions_exam');
            $table->index('section', 'idx_questions_section');
            $table->index('question_type', 'idx_questions_type');
            $table->foreign('exam_id', 'fk_questions_exam')
                ->references('id')->on('exams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
    }
};
