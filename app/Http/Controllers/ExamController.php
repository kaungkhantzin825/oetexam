<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The practice catalogue — port of controllers/examController.js.
 *
 * Two screens live here:
 *   index()  the sub-test chooser + exam cards
 *   play()   find-or-resume an attempt and hand the player its question set
 */
class ExamController extends Controller
{
    /** GET /practice — the exam catalogue, grouped by sub-test. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $exams = Exam::query()
            ->where('is_published', true)
            ->when($request->filled('section'), fn ($q) => $q->where('section', $request->string('section')))
            ->withCount(['activeQuestions as question_count'])
            ->orderByRaw(Exam::sectionOrder())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Exam $exam) => [
                'id'               => $exam->id,
                'title'            => $exam->title,
                'description'      => $exam->description,
                'duration_minutes' => $exam->duration_minutes,
                'section'          => $exam->section,
                'difficulty'       => $exam->difficulty,
                'total_marks'      => $exam->total_marks,
                'question_count'   => $exam->question_count,
                'created_at'       => $exam->created_at?->toDateTimeString(),
            ]);

        // Per-sub-test history so each card can show the student's best band.
        $history = Exam::query()
            ->join('attempts', 'attempts.exam_id', '=', 'exams.id')
            ->where('attempts.user_id', $user->id)
            ->where('attempts.status', 'completed')
            ->groupBy('exams.section')
            ->selectRaw('exams.section, COUNT(attempts.id) as attempts, ROUND(AVG(attempts.band_score),1) as avg_band, MAX(attempts.band_score) as best_band')
            ->get()
            ->keyBy('section');

        $sections = collect(Exam::SECTIONS)->map(function (string $section) use ($exams, $history) {
            $row = $history->get($section);

            return [
                'section'     => $section,
                'exam_count'  => $exams->where('section', $section)->count(),
                'attempts'    => (int) ($row->attempts ?? 0),
                'avg_band'    => $row->avg_band ?? null,
                'best_band'   => $row->best_band ?? null,
            ];
        });

        // Attempts still open, so the button can say "Resume" instead of "Start".
        $inProgress = \App\Models\Attempt::query()
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->pluck('id', 'exam_id');

        return Inertia::render('Student/Practice', [
            'exams'      => $exams,
            'sections'   => $sections,
            'inProgress' => $inProgress,
        ]);
    }

    /**
     * GET /practice/{exam} — start (or resume) an attempt and render the player.
     *
     * Starting the attempt here rather than via a separate XHR keeps the whole
     * flow a normal Inertia navigation: refresh-safe, back-button-safe.
     */
    public function play(Request $request, Exam $exam): Response
    {
        $user = $request->user();

        if (! $exam->is_published && ! $user->isAdmin()) {
            abort(403, 'This exam is not published yet.');
        }

        $attempt = \App\Models\Attempt::firstOrCreate(
            [
                'user_id' => $user->id,
                'exam_id' => $exam->id,
                'status'  => 'in_progress',
            ],
            [
                'started_at' => now(),
                'max_score'  => $exam->total_marks ?? 0,
            ]
        );

        $saved = $attempt->answers()->get()->mapWithKeys(fn ($a) => [
            $a->question_id => [
                'question_id'        => $a->question_id,
                'user_answer'        => $a->user_answer,
                'image_path'         => $a->image_path,
                'is_marked'          => (bool) $a->is_marked,
                'time_spent_seconds' => (int) $a->time_spent_seconds,
            ],
        ]);

        $questions = Question::query()
            ->where('exam_id', $exam->id)
            ->where('is_active', 1)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get()
            ->map(fn (Question $q) => $q->toPlayerArray());

        return Inertia::render('Student/ExamPlayer', [
            'exam' => [
                'id'               => $exam->id,
                'title'            => $exam->title,
                'section'          => $exam->section,
                'description'      => $exam->description,
                'duration_minutes' => $exam->duration_minutes,
            ],
            'attempt' => [
                'id'         => $attempt->id,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'resumed'    => ! $attempt->wasRecentlyCreated,
            ],
            'questions'    => $questions,
            'savedAnswers' => $saved,
        ]);
    }
}
