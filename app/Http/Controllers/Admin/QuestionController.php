<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin question CRUD + bulk creation from AI drafts.
 * Port of controllers/questionController.js.
 *
 * `correct_answer` conventions (unchanged from the Node implementation):
 *   MCQ          -> the 0-based option index as a string ("0".."3")
 *   ShortAnswer  -> the expected literal answer (matched forgivingly)
 *   Writing      -> null (AI-marked)
 *   RolePlay     -> null (AI-marked)
 */
class QuestionController extends Controller
{
    /** GET /admin/questions */
    public function index(Request $request): Response
    {
        $questions = Question::query()
            ->join('exams', 'exams.id', '=', 'questions.exam_id')
            ->when($request->filled('exam_id'), fn ($q) => $q->where('questions.exam_id', $request->integer('exam_id')))
            ->when($request->filled('section'), fn ($q) => $q->where('questions.section', $request->string('section')))
            ->when($request->filled('type'), fn ($q) => $q->where('questions.question_type', $request->string('type')))
            ->when($request->filled('q'), fn ($q) => $q->where('questions.question_text', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('questions.exam_id')
            ->orderBy('questions.order_index')
            ->orderBy('questions.id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Question $q) => [
                'id'             => $q->id,
                'exam_id'        => $q->exam_id,
                'exam_title'     => $q->exam_title,
                'section'        => $q->section,
                'question_type'  => $q->question_type,
                'part'           => $q->part,
                'order_index'    => $q->order_index,
                'question_text'  => $q->question_text,
                'options'        => $q->options,
                'correct_answer' => $q->correct_answer,
                'marks'          => $q->marks,
                'is_active'      => (bool) $q->is_active,
                'has_audio'      => filled($q->audio_url),
                'explanation'    => $q->explanation,
                'explanation_my' => $q->explanation_my,
                'model_answer'   => $q->model_answer,
                'model_answer_my' => $q->model_answer_my,
                'audio_url'      => $q->audio_url,
                'audio_script'   => $q->audio_script,
                'role_play_card' => $q->role_play_card,
            ]);

        return Inertia::render('Admin/Questions', [
            'questions' => $questions,
            'exams'     => Exam::query()->orderByRaw(Exam::sectionOrder())->orderBy('id')->get(['id', 'title', 'section']),
            'filters'   => $request->only(['exam_id', 'section', 'type', 'q']),
        ]);
    }

    /** GET /admin/questions/{question} — used by the editor. */
    public function show(Question $question): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['question' => $question]]);
    }

    /** POST /admin/questions */
    public function store(Request $request)
    {
        $exam = Exam::findOrFail($request->integer('exam_id'));

        $normalised = $this->normaliseInput($request->all());
        $this->validateQuestion($normalised);

        $exam->questions()->create($normalised);

        ExamController::refreshTotals($exam);

        return back()->with('success', 'Question added.');
    }

    /** PUT /admin/questions/{question} */
    public function update(Request $request, Question $question)
    {
        // Merge with the existing row so partial updates work.
        $merged = array_merge(
            $question->only([
                'section', 'question_type', 'part', 'order_index', 'question_text', 'options',
                'correct_answer', 'audio_url', 'audio_script', 'model_answer', 'model_answer_my',
                'role_play_card', 'marks', 'explanation', 'explanation_my', 'is_active',
            ]),
            $request->all()
        );

        $normalised = $this->normaliseInput($merged);
        $this->validateQuestion($normalised);

        $question->update($normalised);

        ExamController::refreshTotals($question->exam);

        return back()->with('success', 'Question updated.');
    }

    /** DELETE /admin/questions/{question} */
    public function destroy(Question $question)
    {
        $exam = $question->exam;
        $question->delete();

        if ($exam) {
            ExamController::refreshTotals($exam);
        }

        return back()->with('success', 'Question deleted.');
    }

    /**
     * POST /admin/questions/bulk
     * Saves a batch of AI-generated drafts in one request. Invalid drafts are
     * skipped rather than failing the whole batch.
     */
    public function bulk(Request $request): JsonResponse
    {
        $request->validate(['exam_id' => ['required', 'integer'], 'questions' => ['required', 'array']]);

        $exam = Exam::findOrFail($request->integer('exam_id'));
        $list = $request->input('questions', []);

        $order = (int) Question::where('exam_id', $exam->id)->max('order_index');

        $inserted = 0;
        $ids = [];

        foreach ($list as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $normalised = $this->normaliseInput(array_merge(
                ['section' => $exam->section],
                $raw,
                ['order_index' => ++$order]
            ));

            try {
                $this->validateQuestion($normalised);
            } catch (\Throwable) {
                continue;
            }

            $question = $exam->questions()->create($normalised);
            $ids[] = $question->id;
            $inserted++;
        }

        ExamController::refreshTotals($exam);

        return response()->json([
            'success' => true,
            'message' => "{$inserted} question(s) added.",
            'data'    => [
                'inserted_count' => $inserted,
                'ids'            => $ids,
                'skipped'        => count($list) - $inserted,
            ],
        ], 201);
    }

    // -----------------------------------------------------------------------

    /** Normalise whatever the admin form sent into DB-ready values. */
    public function normaliseInput(array $body): array
    {
        $options = $body['options'] ?? null;

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $options = $decoded;
            } else {
                // Allow "A | B | C | D" or newline-separated input from a textarea.
                $options = array_values(array_filter(array_map('trim', preg_split('/\r?\n|\|/', $options) ?: [])));
            }
        }
        if (is_array($options) && count($options) === 0) {
            $options = null;
        }

        $rolePlayCard = $body['role_play_card'] ?? null;
        if (is_string($rolePlayCard) && trim($rolePlayCard) !== '') {
            $decoded = json_decode($rolePlayCard, true);
            $rolePlayCard = json_last_error() === JSON_ERROR_NONE ? $decoded : ['task' => $rolePlayCard];
        } elseif (! is_array($rolePlayCard)) {
            $rolePlayCard = null;
        }

        $correct = $body['correct_answer'] ?? null;

        return [
            'section'         => $body['section'] ?? null,
            'question_type'   => $body['question_type'] ?? null,
            'part'            => $body['part'] ?? null,
            'order_index'     => (int) ($body['order_index'] ?? 0),
            'question_text'   => $body['question_text'] ?? null,
            'options'         => $options,
            'correct_answer'  => ($correct === null || $correct === '') ? null : (string) $correct,
            'audio_url'       => $body['audio_url'] ?? null,
            'audio_script'    => $body['audio_script'] ?? null,
            'model_answer'    => $body['model_answer'] ?? null,
            'model_answer_my' => $body['model_answer_my'] ?? null,
            'role_play_card'  => $rolePlayCard,
            'marks'           => (int) ($body['marks'] ?? 1) ?: 1,
            'explanation'     => $body['explanation'] ?? null,
            'explanation_my'  => $body['explanation_my'] ?? null,
            'is_active'       => array_key_exists('is_active', $body) ? (bool) $body['is_active'] : true,
        ];
    }

    /** Shared validation for create/update. Throws a ValidationException. */
    public function validateQuestion(array $q): void
    {
        $errors = [];

        if (! in_array($q['section'], Exam::SECTIONS, true)) {
            $errors['section'] = 'section must be one of: '.implode(', ', Exam::SECTIONS);
        }
        if (! in_array($q['question_type'], Question::TYPES, true)) {
            $errors['question_type'] = 'question_type must be one of: '.implode(', ', Question::TYPES);
        }
        if (! filled($q['question_text'])) {
            $errors['question_text'] = 'question_text is required.';
        }

        if ($q['question_type'] === 'MCQ') {
            if (! is_array($q['options']) || count($q['options']) < 2) {
                $errors['options'] = 'MCQ questions need at least 2 options.';
            }
            if ($q['correct_answer'] === null) {
                $errors['correct_answer'] = 'MCQ questions need a correct_answer.';
            }
        }

        if ($q['question_type'] === 'ShortAnswer' && $q['correct_answer'] === null) {
            $errors['correct_answer'] = 'Short-answer questions need a correct_answer.';
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }
}
