<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The leaderboard view. Guarded so re-running is safe, and it uses
 * CREATE OR REPLACE so an existing view is simply refreshed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW `v_student_leaderboard` AS
SELECT
  u.id            AS user_id,
  u.name,
  u.email,
  COUNT(a.id)     AS attempts_count,
  ROUND(AVG(a.band_score), 1) AS avg_band,
  MAX(a.band_score)           AS best_band
FROM `users` u
LEFT JOIN `attempts` a
  ON a.user_id = u.id AND a.status = 'completed'
WHERE u.role = 'student'
GROUP BY u.id, u.name, u.email
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `v_student_leaderboard`');
    }
};
