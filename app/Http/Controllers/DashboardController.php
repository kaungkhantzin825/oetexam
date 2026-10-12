<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student dashboard + attempt history — port of the getDashboard and
 * listAttempts halves of controllers/attemptController.js.
 */
class DashboardController extends Controller
{
    /** GET /dashboard */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $totals = Attempt::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as tests_taken, ROUND(AVG(band_score),1) as avg_band, MAX(band_score) as best_band, SUM(time_spent_seconds) as total_seconds')
            ->first();

        $bySection = Attempt::query()
            ->join('exams', 'exams.id', '=', 'attempts.exam_id')
            ->where('attempts.user_id', $userId)
            ->where('attempts.status', 'completed')
            ->groupBy('exams.section')
            ->selectRaw('exams.section, COUNT(*) as attempts, ROUND(AVG(attempts.band_score),1) as avg_band, MAX(attempts.band_score) as best_band')
            ->orderByRaw("FIELD(exams.section, 'Listening','Reading','Writing','Speaking','Full')")
            ->get();

        $recent = Attempt::query()
            ->join('exams', 'exams.id', '=', 'attempts.exam_id')
            ->where('attempts.user_id', $userId)
            ->orderByDesc('attempts.started_at')
            ->limit(8)
            ->get([
                'attempts.id', 'attempts.exam_id', 'exams.title', 'exams.section',
                'attempts.finished_at', 'attempts.band_score', 'attempts.total_score',
                'attempts.max_score', 'attempts.status',
            ]);

        // Chronological series (oldest -> newest) for the progress chart.
        $trend = Attempt::query()
            ->join('exams', 'exams.id', '=', 'attempts.exam_id')
            ->where('attempts.user_id', $userId)
            ->where('attempts.status', 'completed')
            ->orderBy('attempts.finished_at')
            ->limit(20)
            ->get(['attempts.id', 'attempts.finished_at', 'attempts.band_score', 'exams.section'])
            ->values()
            ->map(fn ($t, $i) => [
                'label'   => '#'.($i + 1),
                'band'    => (float) $t->band_score,
                'section' => $t->section,
                'date'    => $t->finished_at,
            ]);

        return Inertia::render('Student/Dashboard', [
            'stats' => [
                'tests_taken'   => (int) ($totals->tests_taken ?? 0),
                'avg_band'      => (float) ($totals->avg_band ?? 0),
                'best_band'     => (float) ($totals->best_band ?? 0),
                'total_seconds' => (int) ($totals->total_seconds ?? 0),
            ],
            'bySection'      => $bySection,
            'recentAttempts' => $recent,
            'trend'          => $trend,
        ]);
    }

    /** GET /history — the student's full attempt list. */
    public function history(Request $request): Response
    {
        $attempts = Attempt::query()
            ->join('exams', 'exams.id', '=', 'attempts.exam_id')
            ->where('attempts.user_id', $request->user()->id)
            ->orderByDesc('attempts.started_at')
            ->limit(200)
            ->get([
                'attempts.id', 'attempts.exam_id', 'exams.title', 'exams.section',
                'attempts.started_at', 'attempts.finished_at', 'attempts.time_spent_seconds',
                'attempts.total_score', 'attempts.max_score', 'attempts.band_score',
                'attempts.section_scores', 'attempts.status',
            ]);

        return Inertia::render('Student/History', [
            'attempts' => $attempts,
        ]);
    }

    /** GET /profile */
    public function profile(Request $request): Response
    {
        $userId = $request->user()->id;

        $stats = Attempt::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as tests_taken, ROUND(AVG(band_score),1) as avg_band, MAX(band_score) as best_band')
            ->first();

        $lessonsCompleted = DB::table('lesson_progress')
            ->where('user_id', $userId)
            ->where('completed', 1)
            ->count();

        return Inertia::render('Student/Profile', [
            'stats' => [
                'tests_taken'       => (int) ($stats->tests_taken ?? 0),
                'avg_band'          => (float) ($stats->avg_band ?? 0),
                'best_band'         => (float) ($stats->best_band ?? 0),
                'lessons_completed' => $lessonsCompleted,
            ],
        ]);
    }
}
