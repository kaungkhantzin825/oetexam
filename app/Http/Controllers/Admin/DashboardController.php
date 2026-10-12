<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin overview — port of the stats / leaderboard / ai-logs parts of
 * controllers/adminController.js.
 */
class DashboardController extends Controller
{
    /** GET /admin */
    public function index(): Response
    {
        $users = User::query()
            ->selectRaw("COUNT(*) as total, SUM(role = 'student') as students, SUM(role = 'admin') as admins, SUM(is_active = 1) as active")
            ->first();

        $exams = Exam::query()
            ->selectRaw('COUNT(*) as total, SUM(is_published = 1) as published')
            ->first();

        $questions = Question::query()
            ->selectRaw('COUNT(*) as total, SUM(is_active = 1) as active')
            ->first();

        $attempts = Attempt::query()
            ->selectRaw("COUNT(*) as total, SUM(status = 'completed') as completed, SUM(status = 'in_progress') as in_progress, ROUND(AVG(CASE WHEN status='completed' THEN band_score END),1) as avg_band")
            ->first();

        // Last 14 days of activity for the dashboard chart.
        $activity = Attempt::query()
            ->where('started_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy(DB::raw('DATE(started_at)'))
            ->orderBy(DB::raw('DATE(started_at)'))
            ->selectRaw('DATE(started_at) as day, COUNT(*) as attempts, COUNT(DISTINCT user_id) as students')
            ->get();

        $bySection = Exam::query()
            ->leftJoin('attempts', function ($join) {
                $join->on('attempts.exam_id', '=', 'exams.id')
                    ->where('attempts.status', '=', 'completed');
            })
            ->groupBy('exams.section')
            ->orderByRaw("FIELD(exams.section, 'Listening','Reading','Writing','Speaking','Full')")
            ->selectRaw('exams.section, COUNT(attempts.id) as attempts, ROUND(AVG(attempts.band_score),1) as avg_band')
            ->get();

        $recent = Attempt::query()
            ->join('users', 'users.id', '=', 'attempts.user_id')
            ->join('exams', 'exams.id', '=', 'attempts.exam_id')
            ->orderByDesc('attempts.started_at')
            ->limit(10)
            ->get([
                'attempts.id', 'users.name as student_name', 'exams.title as exam_title',
                'exams.section', 'attempts.band_score', 'attempts.status',
                'attempts.started_at', 'attempts.finished_at',
            ]);

        $leaderboard = DB::table('v_student_leaderboard')
            ->orderByDesc('best_band')
            ->orderByDesc('avg_band')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => [
                    'total'    => (int) ($users->total ?? 0),
                    'students' => (int) ($users->students ?? 0),
                    'admins'   => (int) ($users->admins ?? 0),
                    'active'   => (int) ($users->active ?? 0),
                ],
                'exams'     => ['total' => (int) ($exams->total ?? 0), 'published' => (int) ($exams->published ?? 0)],
                'questions' => ['total' => (int) ($questions->total ?? 0), 'active' => (int) ($questions->active ?? 0)],
                'attempts'  => [
                    'total'       => (int) ($attempts->total ?? 0),
                    'completed'   => (int) ($attempts->completed ?? 0),
                    'in_progress' => (int) ($attempts->in_progress ?? 0),
                    'avg_band'    => (float) ($attempts->avg_band ?? 0),
                ],
            ],
            'activity'      => $activity,
            'bySection'     => $bySection,
            'recentAttempts' => $recent,
            'leaderboard'   => $leaderboard,
        ]);
    }

    /** GET /admin/ai-logs */
    public function aiLogs(): Response
    {
        $logs = AiLog::query()
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'user_id', 'feature', 'model', 'latency_ms', 'success', 'error', 'created_at']);

        $summary = AiLog::query()
            ->groupBy('feature')
            ->orderByRaw('COUNT(*) DESC')
            ->selectRaw('feature, COUNT(*) as calls, ROUND(AVG(latency_ms)) as avg_ms, SUM(success = 0) as failures')
            ->get();

        return Inertia::render('Admin/AiLogs', [
            'logs'    => $logs,
            'summary' => $summary,
        ]);
    }

    /** GET /admin/results */
    public function results(Request $request): Response
    {
        $query = Attempt::query()
            ->join('users', 'users.id', '=', 'attempts.user_id')
            ->join('exams', 'exams.id', '=', 'attempts.exam_id')
            ->where('attempts.status', 'completed')
            ->when($request->filled('exam_id'), fn ($q) => $q->where('attempts.exam_id', $request->integer('exam_id')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('attempts.user_id', $request->integer('student_id')))
            ->when($request->filled('section'), fn ($q) => $q->where('exams.section', $request->string('section')));

        $summary = (clone $query)
            ->selectRaw('ROUND(AVG(attempts.band_score),1) as avg_band, MAX(attempts.band_score) as best_band, COUNT(*) as n')
            ->first();

        $results = $query
            ->orderByDesc('attempts.finished_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($r) => [
                'id'                 => $r->id,
                'user_id'            => $r->user_id,
                'student_name'       => $r->student_name,
                'student_email'      => $r->student_email,
                'exam_id'            => $r->exam_id,
                'exam_title'         => $r->exam_title,
                'section'            => $r->section,
                'total_score'        => (float) $r->total_score,
                'max_score'          => (float) $r->max_score,
                'band_score'         => (float) $r->band_score,
                'section_scores'     => $r->section_scores,
                'time_spent_seconds' => (int) $r->time_spent_seconds,
                'started_at'         => $r->started_at,
                'finished_at'        => $r->finished_at,
            ]);

        return Inertia::render('Admin/Results', [
            'results' => $results,
            'summary' => [
                'avg_band'  => (float) ($summary->avg_band ?? 0),
                'best_band' => (float) ($summary->best_band ?? 0),
                'n'         => (int) ($summary->n ?? 0),
            ],
            'filters' => $request->only(['exam_id', 'student_id', 'section']),
            'exams'   => Exam::query()->orderBy('section')->orderBy('id')->get(['id', 'title', 'section']),
        ]);
    }
}
