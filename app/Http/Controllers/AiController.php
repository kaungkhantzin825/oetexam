<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student-facing AI endpoints — port of the marking half of controllers/aiController.js.
 *
 * Provider routing (Gemini primary, Mistral fallback) happens inside AiManager;
 * the keys never leave the server. The admin-only generation endpoints live in
 * App\Http\Controllers\Admin\AiController.
 */
class AiController extends Controller
{
    public function __construct(private readonly AiManager $ai)
    {
    }

    /** GET /ai/status */
    public function status(): JsonResponse
    {
        $providers = $this->ai->providerStatus();

        return response()->json([
            'success' => true,
            'data'    => [
                'enabled'        => (bool) config('ai.enabled', true),
                'configured'     => $providers['configured'],
                'fallback_armed' => $providers['fallback_armed'],
                'providers'      => $providers['providers'],
                'model'          => $this->ai->modelChain()[0] ?? null,
                'models'         => $this->ai->modelChain(),
                'mistral_models' => $this->ai->mistralModelChain(),
            ],
        ]);
    }

    /** POST /ai/score-writing */
    public function scoreWriting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task'   => ['nullable', 'string'],
            'answer' => ['required', 'string'],
        ], ['answer.required' => 'Please write your letter before requesting feedback.']);

        $this->extendExecutionTime();

        $result = $this->ai->scoreWriting(
            $data['task'] ?? 'OET Writing task',
            $data['answer'],
            $request->user()->id
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /** POST /ai/score-speaking */
    public function scoreSpeaking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_play_card' => ['nullable'],
            'transcript'     => ['required', 'string'],
        ], ['transcript.required' => 'Please provide a transcript of your spoken response.']);

        $this->extendExecutionTime();

        $result = $this->ai->scoreSpeaking(
            $data['role_play_card'] ?? null,
            $data['transcript'],
            $request->user()->id
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * POST /ai/explain
     * The official answer is pulled from the database so it cannot be tampered
     * with by the client.
     */
    public function explain(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'user_answer' => ['nullable', 'string'],
        ]);

        $this->extendExecutionTime();

        $question = Question::find($data['question_id']);
        if (! $question) {
            return response()->json(['success' => false, 'message' => 'Question not found.'], 404);
        }

        try {
            $result = $this->ai->explainAnswer(
                $question->question_text,
                $question->correct_answer,
                $data['user_answer'] ?? null,
                $request->user()->id
            );
        } catch (AiException $e) {
            $status = $e->kind === AiException::TIMEOUT ? 504 : 503;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'details' => ['kind' => $e->kind],
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($result, [
                'official_explanation'    => $question->explanation,
                'official_explanation_my' => $question->explanation_my,
            ]),
        ]);
    }
}
