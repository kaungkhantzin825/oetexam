<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two tutorial tracks.
 *
 * The OET paper is identical for every healthcare profession — a nurse, a
 * pharmacist and a radiographer sit the same test. Until now every lesson on
 * this platform was radiography-flavoured, which quietly taught candidates the
 * wrong lesson: real OET Listening and Reading passages are drawn from the whole
 * of general medicine.
 *
 * `track` separates the two catalogues:
 *
 *   Radiography  profession-specific practice, the original 80 lessons
 *   General      the shared medical paper, profession-neutral
 *
 * Additive and guarded. Existing rows default to 'Radiography', which is
 * correct — everything already in the table is radiography content — so this
 * migration cannot mislabel the shared database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lessons') || Schema::hasColumn('lessons', 'track')) {
            return;
        }

        Schema::table('lessons', function (Blueprint $table) {
            $table->enum('track', ['Radiography', 'General'])
                ->default('Radiography')
                ->after('category')
                ->comment('Which catalogue this lesson belongs to');

            $table->index('track', 'idx_lessons_track');
        });
    }

    public function down(): void
    {
        // Deliberately empty — the shared database is never rolled back.
    }
};
