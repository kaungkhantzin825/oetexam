<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Question;
use App\Services\Ai\AiManager;
use App\Services\Marking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The exam engine — port of controllers/attemptController.js.
 *
 *   save()    upsert answers (used by the periodic auto-save)
 *   submit()  auto-mark MCQ/ShortAnswer, AI-mark Writing/Speaking,
 *             compute section scores + the OET band, close the attempt
 *   result()  the full review payload for the Result & Feedback page
 *
 * The MCQ rule lives in App\Services\Marking and is shared with the result
 * screen, so the marking and the review can never disagree.
 */
class AttemptController extends Controller
{
    public function __construct(private readonly AiManager $ai)
    {
    }

    /** POST /attempts/{attempt}/save — idempotent upsert. */
    public function save(Request $request, Attempt $attempt): JsonResponse
    {
        $this->assertOwned($request, $attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'This attempt has already been submitted.',
            ], 400);
        }

        $saved = $this->persistAnswers($attempt, $request->input('answers', []));

        return response()->json([
            'success' => true,
            'message' => 'Progress saved.',
            'data'    => ['saved' => $saved, 'savedAt' => now()->toIso8601String()],
        ]);
    }

    /**
     * POST /attempts/{attempt}/writing-image
     *
     * Store a photograph of a hand-written letter against one Writing question.
     *
     * The path is written onto the answer row straight away rather than being
     * carried in the client's in-memory answer set, so an auto-save, a refresh
     * or a crash can never lose the photo the student just took.
     */
    public function uploadWritingImage(Request $request, Attempt $attempt): JsonResponse
    {
        $this->assertOwned($request, $attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'This attempt has already been submitted.',
            ], 400);
        }

        $maxKb = (int) config('ai.writing_image.max_kb', 8192);

        $request->validate([
            'image'       => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
            'question_id' => ['required', 'integer'],
        ], [
            'image.required' => 'No photo was received. Use the field name "image".',
            'image.image'    => 'That file is not an image. Upload a photo of your handwritten letter (JPEG, PNG or WebP).',
            'image.mimes'    => 'Only JPEG, PNG and WebP photos are accepted.',
            'image.max'      => 'That photo is larger than '.round($maxKb / 1024).' MB. Please retake it at a smaller size.',
        ]);

        // The question must be a Writing task on THIS attempt's exam — otherwise
        // a student could attach a photo to someone else's exam question.
        $question = Question::query()
            ->where('id', $request->integer('question_id'))
            ->where('exam_id', $attempt->exam_id)
            ->where('question_type', 'Writing')
            ->first();

        if (! $question) {
            return response()->json([
                'success' => false,
                'message' => 'That question is not a Writing task on this exam.',
            ], 422);
        }

        $file = $request->file('image');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = sprintf(
            'q%d-%s-%s.%s',
            $question->id,
            now()->format('YmdHis'),
            substr(sha1(uniqid('', true)), 0, 8),
            preg_replace('/[^a-z0-9]/', '', $extension)
        );

        $directory = public_path('uploads/writing/'.$attempt->id);
        $file->move($directory, $name);

        $relative = 'writing/'.$attempt->id.'/'.$name;

        Answer::updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $question->id],
            [
                'image_path' => $relative,
                // A new photo invalidates the previous transcription.
                'image_transcript' => null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Photo uploaded.',
            'data'    => [
                'question_id' => $question->id,
                'image_path'  => $relative,
                'url'         => '/uploads/'.$relative,
                'size'        => $file->getSize(),
            ],
        ], 201);
    }

    /**
     * DELETE /attempts/{attempt}/writing-image/{question}
     *
     * Remove a photographed answer so the student can go back to typing.
     * Without this, "remove" would be a client-side illusion and the photo
     * would still be marked on submit.
     */
    public function destroyWritingImage(Request $request, Attempt $attempt, Question $question): JsonResponse
    {
        $this->assertOwned($request, $attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'This attempt has already been submitted.',
            ], 400);
        }

        $answer = Answer::query()
            ->where('attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        if ($answer?->image_path) {
            $root = realpath(public_path('uploads/writing'));
            $absolute = realpath(public_path('uploads/'.ltrim((string) $answer->image_path, '/')));

            // Only ever unlink inside the writing-upload directory.
            if ($root !== false && $absolute !== false && str_starts_with($absolute, $root.DIRECTORY_SEPARATOR)) {
                @unlink($absolute);
            }

            $answer->update(['image_path' => null, 'image_transcript' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Photo removed.',
            'data'    => ['question_id' => $question->id],
        ]);
    }

    /** POST /attempts/{attempt}/submit — mark everything and close the attempt. */
    public function submit(Request $request, Attempt $attempt): JsonResponse
    {
        $this->assertOwned($request, $attempt);

        // Idempotent: submitting twice just returns the existing result.
        if ($attempt->status === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'This attempt was already submitted.',
                'data'    => ['attempt_id' => $attempt->id],
            ]);
        }

        // Photographed Writing answers are marked by a vision model, which can
        // take several seconds each — give the request room for the budget.
        $this->extendExecutionTime();

        // 1) Persist whatever the client last had in memory.
        $this->persistAnswers($attempt, $request->input('answers', []));

        // 2) Load the questions and the saved answers.
        $questions = Question::query()
            ->where('exam_id', $attempt->exam_id)
            ->where('is_active', 1)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        $answerByQuestion = $attempt->answers()->get()->keyBy('question_id');

        // 3) Mark each question.
        $totalScore = 0.0;
        $maxScore = 0.0;
        $sectionTotals = [];

        foreach ($questions as $question) {
            $answer = $answerByQuestion->get($question->id);
            $userAnswer = $answer?->user_answer;
            $marks = (int) $question->marks ?: 1;

            $maxScore += $marks;
            $sectionTotals[$question->section] ??= ['score' => 0.0, 'max' => 0.0];
            $sectionTotals[$question->section]['max'] += $marks;

            $isCorrect = null;
            $score = 0.0;
            $feedback = null;
            $transcript = null;

            if ($question->question_type === 'MCQ') {
                $options = $question->options ?? [];
                $chosen = Marking::resolveMcqIndex($userAnswer, $options, $question->correct_answer);
                $correctIdx = Marking::resolveMcqIndex($question->correct_answer, $options, $question->correct_answer);
                $isCorrect = $chosen !== null && $chosen === $correctIdx;
                $score = $isCorrect ? $marks : 0.0;
            } elseif ($question->question_type === 'ShortAnswer') {
                $isCorrect = Marking::isShortAnswerCorrect($userAnswer, $question->correct_answer);
                $score = $isCorrect ? $marks : 0.0;
            } else {
                // Writing / RolePlay — marked by AI (or the heuristic fallback).
                $marking = null;

                if ($question->question_type === 'Writing' && filled($answer?->image_path)) {
                    // A photographed answer. The AI reads the handwriting and
                    // marks it in the same call; the transcription becomes the
                    // student's answer so the review page has something to show.
                    $marking = $this->ai->scoreWritingFromImage(
                        $question->question_text,
                        (string) $answer->image_path,
                        $request->user()->id
                    );

                    $transcript = filled($marking['transcript'] ?? null)
                        ? (string) $marking['transcript']
                        : null;

                    if (! filled($userAnswer) && $transcript !== null) {
                        $userAnswer = $transcript;
                    }
                } elseif (filled($userAnswer)) {
                    $marking = $question->question_type === 'Writing'
                        ? $this->ai->scoreWriting($question->question_text, (string) $userAnswer, $request->user()->id)
                        : $this->ai->scoreSpeaking($question->role_play_card ?? $question->question_text, (string) $userAnswer, $request->user()->id);
                }

                if ($marking !== null) {
                    $band = (float) ($marking['band_score'] ?? 0);
                    $score = Marking::round2(($band / 6) * $marks);
                    $isCorrect = null; // subjective — no right/wrong
                    $feedback = json_encode($marking, JSON_UNESCAPED_UNICODE);
                }
            }

            $totalScore += $score;
            $sectionTotals[$question->section]['score'] += $score;

            Answer::updateOrCreate(
                ['attempt_id' => $attempt->id, 'question_id' => $question->id],
                [
                    'user_answer'      => $userAnswer,
                    'is_correct'       => $isCorrect,
                    'score'            => $score,
                    'max_score'        => $marks,
                    'ai_feedback'      => $feedback,
                    // Never touches image_path: that belongs to the upload.
                    'image_transcript' => $transcript,
                ]
            );
        }

        // 4) Compute section bands and the overall band.
        $sectionScores = [];
        foreach ($sectionTotals as $section => $totals) {
            $pct = $totals['max'] > 0 ? ($totals['score'] / $totals['max']) * 100 : 0;
            $sectionScores[$section] = [
                'score'      => Marking::round2($totals['score']),
                'max'        => Marking::round2($totals['max']),
                'percentage' => Marking::round2($pct),
                'band'       => Marking::percentageToBand($pct),
            ];
        }

        $overallPct = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        $band = Marking::percentageToBand($overallPct);

        $attempt->update([
            'finished_at'        => now(),
            'time_spent_seconds' => (int) $request->input('time_spent_seconds', 0),
            'total_score'        => Marking::round2($totalScore),
            'max_score'          => Marking::round2($maxScore),
            'band_score'         => $band,
            'section_scores'     => $sectionScores,
            'status'             => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exam submitted. Here are your results.',
            'data'    => [
                'attempt_id'     => $attempt->id,
                'total_score'    => Marking::round2($totalScore),
                'max_score'      => Marking::round2($maxScore),
                'percentage'     => Marking::round2($overallPct),
                'band_score'     => $band,
                'section_scores' => $sectionScores,
                'auto_submitted' => (bool) $request->input('auto_submitted', false),
            ],
        ]);
    }

    /** GET /attempts/{attempt}/result — the review page. */
    public function result(Request $request, Attempt $attempt): Response
    {
        // Admins may open any student's review (the admin results table links
        // straight here); a student may only open their own.
        $this->assertOwned($request, $attempt, allowAdmin: true);

        $exam = $attempt->exam;

        $rows = Question::query()
            ->leftJoin('answers', function ($join) use ($attempt) {
                $join->on('answers.question_id', '=', 'questions.id')
                    ->where('answers.attempt_id', '=', $attempt->id);
            })
            ->where('questions.exam_id', $attempt->exam_id)
            ->where('questions.is_active', 1)
            ->orderBy('questions.order_index')
            ->orderBy('questions.id')
            ->select([
                'questions.id as question_id', 'questions.section', 'questions.question_type',
                'questions.part', 'questions.order_index', 'questions.question_text',
                'questions.options', 'questions.correct_answer', 'questions.model_answer',
                'questions.model_answer_my', 'questions.marks', 'questions.explanation',
                'questions.explanation_my', 'questions.audio_url', 'questions.role_play_card',
                'answers.user_answer', 'answers.is_correct', 'answers.score',
                'answers.max_score', 'answers.is_marked', 'answers.ai_feedback',
                'answers.image_path', 'answers.image_transcript',
            ])
            ->get();

        $questions = $rows->map(function ($r) {
            $options = is_string($r->options) ? json_decode($r->options, true) : $r->options;
            $rolePlay = is_string($r->role_play_card) ? json_decode($r->role_play_card, true) : $r->role_play_card;
            $feedback = is_string($r->ai_feedback) ? json_decode($r->ai_feedback, true) : $r->ai_feedback;

            return [
                'question_id'         => $r->question_id,
                'section'             => $r->section,
                'question_type'       => $r->question_type,
                'part'                => $r->part,
                'question_text'       => $r->question_text,
                'options'             => $options,
                'audio_url'           => $r->audio_url,
                'role_play_card'      => $rolePlay,
                'marks'               => (int) $r->marks,
                'user_answer'         => $r->user_answer,
                'correct_answer'      => $r->correct_answer,
                'correct_option_index' => $options ? Marking::resolveMcqIndex($r->correct_answer, $options, $r->correct_answer) : null,
                'chosen_option_index' => $options ? Marking::resolveMcqIndex($r->user_answer, $options, $r->correct_answer) : null,
                'is_correct'          => $r->is_correct === null ? null : (bool) $r->is_correct,
                'score'               => (float) $r->score,
                'max_score'           => (float) $r->max_score,
                'explanation'         => $r->explanation,
                'explanation_my'      => $r->explanation_my,
                'model_answer'        => $r->model_answer,
                'model_answer_my'     => $r->model_answer_my,
                'ai_feedback'         => $feedback,
                // A photographed hand-written answer, if one was uploaded.
                'image_path'          => $r->image_path,
                'image_url'           => $r->image_path ? '/uploads/'.ltrim((string) $r->image_path, '/') : null,
                'image_transcript'    => $r->image_transcript,
            ];
        });

        $objective = $questions->filter(fn ($q) => in_array($q['question_type'], ['MCQ', 'ShortAnswer'], true));
        $correctCount = $objective->where('is_correct', true)->count();
        // A photo of a hand-written letter is an answer even with no text in it.
        $answered = $questions->filter(fn ($q) => filled($q['user_answer']) || filled($q['image_path']));

        return Inertia::render('Student/Result', [
            'attempt' => [
                'id'                 => $attempt->id,
                'exam_id'            => $attempt->exam_id,
                'started_at'         => $attempt->started_at?->toDateTimeString(),
                'finished_at'        => $attempt->finished_at?->toDateTimeString(),
                'time_spent_seconds' => $attempt->time_spent_seconds,
                'total_score'        => $attempt->total_score,
                'max_score'          => $attempt->max_score,
                'band_score'         => $attempt->band_score,
                'section_scores'     => $attempt->section_scores ?? [],
                'status'             => $attempt->status,
            ],
            'exam' => [
                'id'          => $exam->id,
                'title'       => $exam->title,
                'section'     => $exam->section,
                'description' => $exam->description,
            ],
            'summary' => [
                'answered'          => $answered->count(),
                'total_questions'   => $questions->count(),
                'correct_objective' => $correctCount,
                'total_objective'   => $objective->count(),
                'accuracy'          => $objective->count() ? Marking::round2(($correctCount / $objective->count()) * 100) : 0,
            ],
            'questions' => $questions->values(),
        ]);
    }

    // -----------------------------------------------------------------------

    /** Bulk upsert, matching the original INSERT … ON DUPLICATE KEY UPDATE. */
    private function persistAnswers(Attempt $attempt, array $list): int
    {
        $rows = [];
        foreach ($list as $item) {
            if (! is_array($item) || empty($item['question_id'])) {
                continue;
            }
            $rows[] = [
                'attempt_id'         => $attempt->id,
                'question_id'        => (int) $item['question_id'],
                'user_answer'        => $item['user_answer'] === null ? null : (string) $item['user_answer'],
                'is_marked'          => ! empty($item['is_marked']) ? 1 : 0,
                'time_spent_seconds' => (int) ($item['time_spent_seconds'] ?? 0),
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        Answer::upsert($rows, ['attempt_id', 'question_id'], ['user_answer', 'is_marked', 'time_spent_seconds']);

        return count($rows);
    }

    private function assertOwned(Request $request, Attempt $attempt, bool $allowAdmin = false): void
    {
        if ($attempt->user_id === $request->user()->id) {
            return;
        }

        if ($allowAdmin && $request->user()->isAdmin()) {
            return;
        }

        abort(403, 'This attempt belongs to another student.');
    }
}
