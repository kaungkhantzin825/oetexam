<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-only AI content generation — port of the generation half of
 * controllers/aiController.js.
 *
 * Every endpoint returns DRAFTS; nothing is written to the database until the
 * admin saves. Provider routing happens inside AiManager.
 */
class AiController extends Controller
{
    public function __construct(private readonly AiManager $ai)
    {
    }

    /** POST /admin/ai/generate-questions */
    public function generateQuestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section'       => ['nullable', 'string'],
            'count'         => ['nullable', 'integer', 'min:1', 'max:20'],
            'question_type' => ['nullable', 'string'],
            'topic'         => ['nullable', 'string'],
            'transcript'    => ['nullable', 'string'],
        ]);

        $this->extendExecutionTime();

        if ($guard = $this->requireAi()) {
            return $guard;
        }

        try {
            $questions = $this->ai->generateQuestions(
                $data['section'] ?? 'Reading',
                (int) ($data['count'] ?? 5),
                $data['question_type'] ?? 'MCQ',
                $data['topic'] ?? 'General radiography',
                $data['transcript'] ?? '',
                $request->user()->id
            );
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Drafted '.count($questions).' question(s). Review them, then save to the exam.',
            'data'    => ['questions' => $questions],
        ]);
    }

    /** POST /admin/ai/generate-mock */
    public function generateMock(Request $request): JsonResponse
    {
        $this->extendExecutionTime();

        if ($guard = $this->requireAi()) {
            return $guard;
        }

        try {
            $blueprint = $this->ai->generateMockExam(
                (array) $request->input('focus_areas', []),
                $request->user()->id
            );
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        return response()->json(['success' => true, 'data' => $blueprint]);
    }

    /** POST /admin/ai/generate-lesson */
    public function generateLesson(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topic'    => ['required', 'string', 'max:300'],
            'category' => ['nullable', 'string'],
            'track'    => ['nullable', 'string'],
            'level'    => ['nullable', 'string'],
            'notes'    => ['nullable', 'string', 'max:2000'],
        ], ['topic.required' => 'A lesson topic is required.']);

        $this->extendExecutionTime();

        if ($guard = $this->requireAi()) {
            return $guard;
        }

        try {
            $lesson = $this->ai->generateLesson(
                trim($data['topic']),
                in_array($data['category'] ?? null, AiManager::LESSON_CATEGORIES, true) ? $data['category'] : 'ExamStrategy',
                in_array($data['level'] ?? null, ['beginner', 'intermediate', 'advanced'], true) ? $data['level'] : 'beginner',
                (string) ($data['notes'] ?? ''),
                $request->user()->id,
                // The track decides the examiner persona: radiography-specific or
                // the profession-neutral shared paper.
                in_array($data['track'] ?? null, Lesson::TRACKS, true) ? $data['track'] : Lesson::DEFAULT_TRACK
            );
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft lesson ready — review the English and Myanmar versions, then save.',
            'data'    => ['lesson' => $lesson],
        ]);
    }

    /** POST /admin/ai/translate */
    public function translate(Request $request): JsonResponse
    {
        $payload = $request->input('payload');

        if (! is_array($payload) || array_is_list($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Body must be { payload: { key: "text" } }.',
            ], 422);
        }

        $this->extendExecutionTime();

        if ($guard = $this->requireAi()) {
            return $guard;
        }

        // Keep a single request inside a sane size — the caller can batch.
        $trimmed = [];
        foreach (array_slice($payload, 0, 25, true) as $key => $value) {
            $trimmed[$key] = mb_substr((string) $value, 0, 12000);
        }

        // The track picks the translator persona, so a General lesson is not
        // translated as if it were radiography content.
        $track = in_array($request->input('track'), Lesson::TRACKS, true)
            ? $request->input('track')
            : Lesson::DEFAULT_TRACK;

        try {
            $translations = $this->ai->translateToMyanmar($trimmed, $request->user()->id, 'translate_my', $track);
        } catch (AiException $e) {
            return $this->aiError($e);
        }

        return response()->json(['success' => true, 'data' => ['translations' => $translations]]);
    }

    // -----------------------------------------------------------------------

    /** Every generation endpoint fails with one clear message when AI is off. */
    private function requireAi(): ?JsonResponse
    {
        if ($this->ai->isConfigured()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'AI is not configured. Add GEMINI_API_KEY (and optionally MISTRAL_API_KEY) to the environment and restart.',
        ], 503);
    }

    private function aiError(AiException $e): JsonResponse
    {
        $status = match ($e->kind) {
            AiException::DAILY_QUOTA => 429,
            AiException::RATE_LIMIT  => 429,
            AiException::TIMEOUT     => 504,
            AiException::TRANSIENT   => 503,
            default                  => 502,
        };

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'details' => ['kind' => $e->kind, 'provider_code' => $e->providerCode],
        ], $status);
    }
}
