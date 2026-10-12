<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Handwritten Writing answers.
 *
 * A candidate may write the OET referral letter on paper, photograph it and
 * upload the photo instead of typing. The image itself lives under
 * `public/uploads/writing/{attempt_id}/`; the answer row keeps the relative
 * path plus the AI's transcription of the handwriting, so the review page can
 * show both the photo and what the AI actually read.
 *
 * Additive and guarded. `answers` is pre-existing shared data that is never
 * rebuilt, and MariaDB 10.4 rejects `ADD COLUMN IF NOT EXISTS` through the
 * prepared-statement protocol, so each column is probed with hasColumn()
 * instead. Re-running `php artisan migrate` is therefore a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('answers')) {
            return;
        }

        $missing = array_filter(
            ['image_path', 'image_transcript'],
            fn (string $column) => ! Schema::hasColumn('answers', $column)
        );

        if ($missing === []) {
            return;
        }

        Schema::table('answers', function (Blueprint $table) use ($missing) {
            if (in_array('image_path', $missing, true)) {
                $table->string('image_path', 255)
                    ->nullable()
                    ->after('user_answer')
                    ->comment('Relative path under public/uploads/ of a photographed answer');
            }

            if (in_array('image_transcript', $missing, true)) {
                $table->text('image_transcript')
                    ->nullable()
                    ->after('image_path')
                    ->comment('AI transcription of the uploaded handwriting');
            }
        });
    }

    public function down(): void
    {
        // Deliberately empty — the shared database is never rolled back.
    }
};
