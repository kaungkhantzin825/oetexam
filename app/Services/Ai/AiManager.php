<?php

namespace App\Services\Ai;

use Throwable;

/**
 * The single AI facade for the whole application — port of utils/ai.js.
 *
 * Routing policy
 *   1. Try Google Gemini (primary).
 *   2. If Gemini is unavailable, errors out, or has burned its free-tier daily
 *      quota, hand the *same prompt* to Mistral AI (fallback).
 *   3. If both providers fail, throw a classified error. The domain methods
 *      below additionally degrade to a deterministic offline heuristic so the
 *      exam platform never becomes unusable.
 *
 * Callers use `AiManager` only; the providers know nothing about OET.
 */
class AiManager
{
    /** Ordered provider chain — primary first. */
    protected array $providers;

    public function __construct(GeminiProvider $gemini, MistralProvider $mistral)
    {
        $this->providers = [$gemini, $mistral];
    }

    /** Providers that currently have a usable key + feature flag. */
    public function activeProviders(): array
    {
        return array_values(array_filter($this->providers, fn (Provider $p) => $p->isConfigured()));
    }

    public function isConfigured(): bool
    {
        return count($this->activeProviders()) > 0;
    }

    /** True when the Mistral fallback is armed. */
    public function hasFallback(): bool
    {
        return $this->providers[1]->isConfigured();
    }

    /** Describe the routing state — surfaced to the admin dashboard. */
    public function providerStatus(): array
    {
        return [
            'configured'     => $this->isConfigured(),
            'fallback_armed' => $this->hasFallback(),
            'providers'      => array_map(fn (Provider $p) => [
                'name'       => $p->name(),
                'configured' => $p->isConfigured(),
                'models'     => $p->modelChain(),
            ], $this->providers),
        ];
    }

    /** The ordered Gemini model chain (primary provider). */
    public function modelChain(): array
    {
        return $this->providers[0]->modelChain();
    }

    public function mistralModelChain(): array
    {
        return $this->providers[1]->modelChain();
    }

    /** True when the request carries at least one usable image part. */
    private function hasAttachments(array $opts): bool
    {
        foreach ((array) ($opts['attachments'] ?? []) as $attachment) {
            if (is_array($attachment) && ($attachment['data'] ?? '') !== '' && ($attachment['mime'] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Run a prompt through the provider chain, recording which provider answered.
     *
     * One wall-clock Budget is created here and shared by every provider, so the
     * fallback only gets the time Gemini did not spend. Without that, each
     * provider would restart the clock and the request could outlive PHP's
     * execution limit — see Budget.
     *
     * @return array{data: mixed, provider: string, model: string, fallbackUsed: bool}
     *
     * @throws AiException
     */
    public function generateWithMeta(string $prompt, array $opts = []): array
    {
        $available = $this->activeProviders();

        if (empty($available)) {
            throw new AiException(
                'No AI provider is configured. Add GEMINI_API_KEY (and optionally MISTRAL_API_KEY) to the environment.',
                AiException::FATAL,
                'AI_NOT_CONFIGURED'
            );
        }

        // A vision request must only reach a provider that can actually see the
        // image. Handing a photographed answer to a text-only transport would
        // silently mark an empty response, which is worse than failing.
        if ($this->hasAttachments($opts)) {
            $available = array_values(array_filter($available, fn (Provider $p) => $p->supportsVision()));

            if (empty($available)) {
                throw new AiException(
                    'Reading handwriting needs an AI provider with image support. Configure GEMINI_API_KEY, '
                    .'or type the answer instead of uploading a photo.',
                    AiException::FATAL,
                    'VISION_UNSUPPORTED'
                );
            }
        }

        $budget = $opts['budget'] ?? Budget::fromConfig();
        $opts['budget'] = $budget;

        $failures = [];
        $lastError = null;

        foreach ($available as $provider) {
            // Do not start a provider that cannot possibly finish.
            if ($budget->exhausted()) {
                $failures[] = sprintf(
                    '%s: skipped, only %.1fs of the %.0fs budget left',
                    $provider->name(),
                    $budget->remaining(),
                    $budget->seconds()
                );
                break;
            }

            try {
                $result = $provider->run($prompt, $opts);

                $isFallback = $provider !== $this->providers[0];

                if ($isFallback) {
                    logger()->warning(sprintf(
                        '[ai] served by fallback provider "%s" (%s) after %s',
                        $provider->name(),
                        $result['model'],
                        implode('; ', $failures)
                    ));
                }

                return [
                    'data'         => $result['data'],
                    'provider'     => $provider->name(),
                    'model'        => $result['model'],
                    'fallbackUsed' => $isFallback,
                ];
            } catch (AiException $e) {
                // Keep going — the next provider gets the same prompt.
                $lastError = $e;
                $failures[] = $provider->name().': '.$e->getMessage();
            }
        }

        // Surface the most specific cause when the whole thing was simply slow,
        // so callers can distinguish "out of time" from "misconfigured".
        if ($lastError !== null && $lastError->kind === AiException::TIMEOUT) {
            throw new AiException(
                'All AI providers failed. '.implode(' | ', $failures),
                AiException::TIMEOUT,
                'AI_ALL_PROVIDERS_FAILED',
                504
            );
        }

        throw new AiException(
            'All AI providers failed. '.implode(' | ', $failures),
            AiException::FATAL,
            'AI_ALL_PROVIDERS_FAILED'
        );
    }

    /** Convenience wrapper returning only the payload. */
    public function generate(string $prompt, array $opts = [])
    {
        return $this->generateWithMeta($prompt, $opts)['data'];
    }

    // =======================================================================
    // Domain prompts
    // =======================================================================

    public const RADIOGRAPHY_CONTEXT = <<<'TXT'
You are an experienced OET (Occupational English Test) item writer and examiner
specialising in the RADIOGRAPHY / MEDICAL IMAGING profession.
Reference realistic radiography contexts: X-ray, CT, MRI, ultrasound,
mammography, fluoroscopy, DEXA, nuclear medicine, interventional radiology,
radiation safety and ALARA, patient positioning, contrast media, infection
control, PACS/RIS, and communication with radiologists, referrers, nurses and
patients.
Use British English spelling, metric units and professional register.
TXT;

    /** Shared tone rules for the bilingual tutorial author. */
    public const BILINGUAL_RULES = <<<'TXT'
LANGUAGE RULES — this platform serves Myanmar healthcare students:
- "en" fields must be clear, natural English at B2/C1 level, British spelling.
- "my" fields must be natural Burmese (မြန်မာစာ), written the way a teacher would
  explain it to a nursing/radiography student. Do NOT transliterate English into
  Burmese script word-by-word; translate the meaning. Keep established clinical
  terms in English inside parentheses on first use, e.g. ရင်ဘတ်ဓာတ်မှန်ရိုက်ခြင်း
  (chest X-ray).
- Both languages must carry the SAME information — never shorten the Burmese.
- Content may use Markdown (##, **bold**, - bullets, tables). Keep it clean.
TXT;

    /**
     * The General medical track's context.
     *
     * The OET paper is identical for every healthcare profession — a nurse, a
     * pharmacist and a radiographer sit the same test — and its Listening and
     * Reading passages come from the whole of general medicine. This context
     * exists so the General track does not drift back into imaging vocabulary,
     * which would leave candidates unprepared for the real paper.
     */
    public const GENERAL_MEDICAL_CONTEXT = <<<'TXT'
You are an experienced OET (Occupational English Test) item writer and examiner
covering the FULL RANGE of healthcare professions. The OET paper is the same for
every candidate — nurses, doctors, pharmacists, physiotherapists, dentists,
dietitians, occupational therapists, paramedics, midwives and radiographers all
sit the identical test — so this material must be profession-neutral.
Reference realistic general medical contexts: ward and clinic consultations,
medication counselling and review, discharge planning, chronic disease
management, public health and screening, multidisciplinary team meetings,
telephone triage, and communication with patients, families and colleagues.
Do NOT lean on imaging, radiation or any other single profession's vocabulary.
Use British English spelling, metric units and professional register.
TXT;

    /** @var array<string, string> */
    public const TRACK_CONTEXTS = [
        'Radiography' => self::RADIOGRAPHY_CONTEXT,
        'General'     => self::GENERAL_MEDICAL_CONTEXT,
    ];

    /** The audience line, which changes with the track. */
    private const TRACK_AUDIENCES = [
        'Radiography' => 'Myanmar radiography students',
        'General'     => 'Myanmar healthcare students preparing for the shared OET paper',
    ];

    public const LESSON_CATEGORIES = [
        'Listening',
        'Reading',
        'Writing',
        'Speaking',
        'Grammar',
        'Vocabulary',
        'ExamStrategy',
    ];

    // -----------------------------------------------------------------------
    // Questions
    // -----------------------------------------------------------------------

    /** Generate OET-style questions for a given section. */
    public function generateQuestions(
        string $section = 'Reading',
        int $count = 5,
        string $questionType = 'MCQ',
        string $topic = 'General radiography',
        string $transcript = '',
        ?int $userId = null
    ): array {
        $context = self::RADIOGRAPHY_CONTEXT;
        $material = $transcript !== '' ? "Base the questions on this material:\n\"\"\"\n{$transcript}\n\"\"\"" : '';

        $prompt = <<<PROMPT
{$context}

TASK: Write {$count} original OET-style {$section} questions for radiographers.
Question type: {$questionType}.
Topic / clinical focus: {$topic}.
{$material}

Return ONLY valid JSON — an array of {$count} objects with EXACTLY these keys:
[
  {
    "question_text": "the question stem",
    "options": ["option A", "option B", "option C", "option D"],
    "correct_answer": "the full text of the correct option, or the literal expected short answer",
    "explanation": "2-3 sentences explaining why this is correct and the others are not",
    "explanation_my": "the same explanation written in natural Burmese (မြန်မာစာ)",
    "model_answer": "for Writing/RolePlay only: a strong sample answer; otherwise null",
    "part": "e.g. Reading Part B"
  }
]
Rules:
- For MCQ provide exactly 4 plausible options and make exactly one correct.
- For ShortAnswer set "options" to null and keep the answer to <= 5 words.
- For Writing provide a professional referral/letter task and a full model_answer.
- For RolePlay describe the role-play card in question_text and give a model_answer.
- Never include commentary outside the JSON array.
PROMPT;

        $result = $this->generateWithMeta($prompt, [
            'feature' => "generate_questions:{$section}",
            'userId'  => $userId,
            'json'    => true,
        ]);

        $data = $result['data'];
        $list = is_array($data) && array_is_list($data) ? $data : ($data['questions'] ?? []);

        return array_slice(array_map(fn ($q) => [
            'section'        => $section,
            'question_type'  => $questionType,
            'part'           => $q['part'] ?? "{$section} — AI generated",
            'question_text'  => $q['question_text'] ?? '',
            'options'        => is_array($q['options'] ?? null) ? $q['options'] : null,
            'correct_answer' => $q['correct_answer'] ?? null,
            'model_answer'   => $q['model_answer'] ?? null,
            'explanation'    => $q['explanation'] ?? null,
            'explanation_my' => $q['explanation_my'] ?? null,
            'marks'          => 1,
        ], $list), 0, $count);
    }

    // -----------------------------------------------------------------------
    // Writing / Speaking marking
    // -----------------------------------------------------------------------

    /**
     * Score an OET Writing letter against the official criteria.
     * Never throws — falls back to a length heuristic so marking never blocks.
     */
    public function scoreWriting(string $task, string $answer, ?int $userId = null): array
    {
        $context = self::RADIOGRAPHY_CONTEXT;

        $prompt = <<<PROMPT
{$context}

You are an OET Writing examiner. Assess the candidate's letter using the
official OET Writing assessment criteria.

TASK PROMPT GIVEN TO CANDIDATE:
"""
{$task}
"""

CANDIDATE'S LETTER:
"""
{$answer}
"""

Score each criterion on the official scale and return ONLY valid JSON:
{
  "criteria": {
    "purpose":            { "score": 0, "max": 3, "comment": "..." },
    "content":            { "score": 0, "max": 7, "comment": "..." },
    "conciseness":        { "score": 0, "max": 7, "comment": "..." },
    "genre_style":        { "score": 0, "max": 7, "comment": "..." },
    "organisation":       { "score": 0, "max": 7, "comment": "..." },
    "language":           { "score": 0, "max": 7, "comment": "..." }
  },
  "raw_total": 0,
  "band_score": 0.0,
  "strengths": ["...", "..."],
  "improvements": ["...", "..."],
  "corrected_sentences": [
    { "original": "...", "corrected": "...", "reason": "..." }
  ],
  "model_answer": "a full band-6.0 model letter for this task",
  "overall_feedback": "a short paragraph addressed to the candidate"
}
band_score must be a number between 0.0 and 6.0 in 0.5 steps.
Return JSON only — no markdown, no commentary.
PROMPT;

        try {
            $result = $this->generateWithMeta($prompt, [
                'feature' => 'score_writing',
                'userId'  => $userId,
                'json'    => true,
            ]);

            return array_merge(['ok' => true, 'source' => $result['provider']], (array) $result['data']);
        } catch (Throwable $e) {
            return $this->writingHeuristic($answer, $e->getMessage());
        }
    }

    /**
     * Mark a photographed handwritten letter.
     *
     * The candidate writes the referral letter on paper, photographs it and
     * uploads the photo. One call does the two things that must not be
     * separated: it transcribes the handwriting and it marks that transcription
     * against the six official OET Writing criteria. Splitting them would mean
     * marking text nobody had checked.
     *
     * Only Gemini can do this — generateWithMeta() routes a request carrying
     * attachments away from any text-only provider.
     *
     * @param  string  $imagePath  path relative to public/uploads/, e.g. "writing/12/q34-ab12cd.jpg"
     */
    public function scoreWritingFromImage(string $task, string $imagePath, ?int $userId = null): array
    {
        $attachment = $this->imageAttachment($imagePath);

        if ($attachment === null) {
            return $this->writingImageFailure(
                'The uploaded photo could not be read from the server ('.$imagePath.').'
            );
        }

        $context = self::RADIOGRAPHY_CONTEXT;

        $prompt = <<<PROMPT
{$context}

You are an OET Writing examiner. The candidate hand-wrote their referral letter
on paper and photographed it. The photograph is attached.

STEP 1 — Transcribe the handwriting exactly, preserving line breaks and
paragraphs. Transcribe what is actually written, NOT what it should have said:
keep every spelling, grammar and punctuation error exactly as it appears. If a
word is genuinely unreadable write [illegible] in its place and never guess.

STEP 2 — Mark that transcription against the official OET Writing criteria.
The task the candidate was answering was:
"""
{$task}
"""

Return ONLY valid JSON:
{
  "legible": true,
  "handwriting_confidence": "high",
  "transcript": "the full transcription of the letter, with line breaks preserved",
  "legibility_notes": "anything that made the writing hard to read, or an empty string",
  "criteria": {
    "purpose":            { "score": 0, "max": 3, "comment": "..." },
    "content":            { "score": 0, "max": 7, "comment": "..." },
    "conciseness":        { "score": 0, "max": 7, "comment": "..." },
    "genre_style":        { "score": 0, "max": 7, "comment": "..." },
    "organisation":       { "score": 0, "max": 7, "comment": "..." },
    "language":           { "score": 0, "max": 7, "comment": "..." }
  },
  "raw_total": 0,
  "band_score": 0.0,
  "strengths": ["...", "..."],
  "improvements": ["...", "..."],
  "corrected_sentences": [
    { "original": "...", "corrected": "...", "reason": "..." }
  ],
  "model_answer": "a full band-6.0 model letter for this task",
  "overall_feedback": "a short paragraph addressed to the candidate"
}
`handwriting_confidence` is one of "high", "medium" or "low".
`band_score` is a number between 0.0 and 6.0 in 0.5 steps, and must be 0 when
`legible` is false.
Return JSON only — no markdown, no commentary.
PROMPT;

        try {
            $result = $this->generateWithMeta($prompt, [
                'feature'     => 'score_writing_image',
                'userId'      => $userId,
                'json'        => true,
                'attachments' => [$attachment],
            ]);

            return array_merge(
                [
                    'ok'      => true,
                    'source'  => $result['provider'],
                    'model'   => $result['model'],
                    'image'   => $imagePath,
                ],
                (array) $result['data']
            );
        } catch (AiException $e) {
            return $this->writingImageFailure($e->getMessage(), $e->providerCode);
        } catch (Throwable $e) {
            return $this->writingImageFailure($e->getMessage());
        }
    }

    /**
     * Build a Gemini `inlineData` part from a stored upload.
     *
     * Returns null rather than guessing when the file is gone or is not an
     * image — a wrong MIME type produces an opaque 400 from Google that reads
     * like a prompt problem.
     *
     * @return array{mime: string, data: string}|null
     */
    private function imageAttachment(string $imagePath): ?array
    {
        $relative = ltrim(str_replace('\\', '/', $imagePath), '/');

        // The path is ours, but a traversal here would read arbitrary files.
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $absolute = public_path('uploads/'.$relative);
        if (! is_file($absolute)) {
            return null;
        }

        $mime = (string) (@mime_content_type($absolute) ?: '');
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        $bytes = @file_get_contents($absolute);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return ['mime' => $mime, 'data' => base64_encode($bytes)];
    }

    /**
     * A photographed answer that could not be marked.
     *
     * Deliberately NOT a heuristic. There is no transcription to estimate from,
     * and inventing a band from a photo nobody read would be a lie — so the
     * band is 0 and the student is told exactly what happened.
     */
    private function writingImageFailure(string $error, ?string $code = null): array
    {
        $notice = match ($code) {
            'VISION_UNSUPPORTED' => 'The AI service cannot read photographs right now, so this photo could not be marked. Please type your letter instead.',
            'AI_NOT_CONFIGURED'  => 'AI marking is not configured on this server, so this photo could not be marked.',
            default              => 'The AI could not read this photograph, so it could not be marked. Try again with a sharper, straighter photo in good light — or type your letter instead.',
        };

        return [
            'ok'                     => false,
            'source'                 => 'none',
            'band_score'             => 0.0,
            'transcript'             => null,
            'legible'                => false,
            'handwriting_confidence' => 'low',
            'legibility_notes'       => '',
            'criteria'               => [],
            'raw_total'              => 0,
            'strengths'              => [],
            'improvements'           => [],
            'corrected_sentences'    => [],
            'model_answer'           => '',
            'notice'                 => $notice,
            'overall_feedback'       => $notice,
            'error'                  => $error,
        ];
    }

    /** Deterministic offline fallback so a student always gets a score. */
    private function writingHeuristic(string $answer, string $error): array
    {
        $words = count(array_filter(preg_split('/\s+/u', trim($answer)) ?: [], fn ($w) => $w !== ''));
        $band = $words < 80 ? 3.0 : ($words < 140 ? 4.0 : ($words < 200 ? 5.0 : 5.5));

        return [
            'ok'        => true,
            'source'    => 'heuristic',
            'notice'    => 'AI marking is unavailable right now, so an estimated score based on length and structure is shown.',
            'criteria'  => [
                'purpose'      => ['score' => min(3, (int) round($band / 2)), 'max' => 3, 'comment' => 'Estimated.'],
                'content'      => ['score' => (int) round($band + 1), 'max' => 7, 'comment' => 'Estimated.'],
                'conciseness'  => ['score' => (int) round($band), 'max' => 7, 'comment' => 'Estimated.'],
                'genre_style'  => ['score' => (int) round($band), 'max' => 7, 'comment' => 'Estimated.'],
                'organisation' => ['score' => (int) round($band), 'max' => 7, 'comment' => 'Estimated.'],
                'language'     => ['score' => (int) round($band), 'max' => 7, 'comment' => 'Estimated.'],
            ],
            'band_score'          => $band,
            'strengths'           => ['You produced a response of reasonable length.'],
            'improvements'        => [
                'Add a clear purpose statement in the opening line.',
                'Use headings to organise clinical information.',
            ],
            'corrected_sentences' => [],
            'model_answer'        => '',
            'overall_feedback'    => 'AI feedback could not be generated (no AI provider is reachable). This is an automatically estimated score — check GEMINI_API_KEY and MISTRAL_API_KEY in the environment.',
            'error'               => $error,
        ];
    }

    /** Give structured feedback on a Speaking role-play transcript. */
    public function scoreSpeaking($rolePlayCard, string $transcript, ?int $userId = null): array
    {
        $context = self::RADIOGRAPHY_CONTEXT;
        $card = is_string($rolePlayCard) ? $rolePlayCard : json_encode($rolePlayCard, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
{$context}

You are an OET Speaking examiner. Assess the candidate's role-play performance.

ROLE-PLAY CARD:
"""
{$card}
"""

CANDIDATE'S SPOKEN RESPONSE (transcript):
"""
{$transcript}
"""

OET Speaking criteria: Intelligibility, Fluency, Appropriateness of Language,
Resources of Grammar and Expression, and (in the role-play) Relationship-building
and task achievement.

Return ONLY valid JSON:
{
  "criteria": [
    { "name": "Intelligibility", "score": 0, "max": 3, "comment": "..." },
    { "name": "Fluency", "score": 0, "max": 3, "comment": "..." },
    { "name": "Appropriateness", "score": 0, "max": 3, "comment": "..." },
    { "name": "Grammar & Expression", "score": 0, "max": 3, "comment": "..." },
    { "name": "Relationship-building", "score": 0, "max": 3, "comment": "..." },
    { "name": "Task achievement", "score": 0, "max": 3, "comment": "..." }
  ],
  "band_score": 0.0,
  "strengths": ["..."],
  "improvements": ["..."],
  "useful_phrases": ["..."],
  "overall_feedback": "..."
}
band_score between 0.0 and 6.0 in 0.5 steps. JSON only.
PROMPT;

        try {
            $result = $this->generateWithMeta($prompt, [
                'feature' => 'score_speaking',
                'userId'  => $userId,
                'json'    => true,
            ]);

            return array_merge(['ok' => true, 'source' => $result['provider']], (array) $result['data']);
        } catch (Throwable $e) {
            return [
                'ok'            => true,
                'source'        => 'heuristic',
                'notice'        => 'AI speaking feedback is unavailable; showing general guidance instead.',
                'criteria'      => [],
                'band_score'    => 4.0,
                'strengths'     => ['You completed the task.'],
                'improvements'  => ['Signpost the consultation: greet, explain, check understanding, summarise, close.'],
                'useful_phrases' => [
                    'Good morning, my name is ... and I am one of the radiographers here.',
                    'Before we begin, may I confirm your details?',
                    'I am going to explain what will happen during your scan.',
                    'Do you have any questions before we start?',
                ],
                'overall_feedback' => 'AI feedback could not be generated. Check GEMINI_API_KEY and MISTRAL_API_KEY to receive examiner-level feedback on your speaking performance.',
                'error'         => $e->getMessage(),
            ];
        }
    }

    /** Explain a single answer (why correct / why the student's choice was wrong). */
    public function explainAnswer(string $question, $correctAnswer, $userAnswer, ?int $userId = null): array
    {
        $context = self::RADIOGRAPHY_CONTEXT;
        $correct = is_scalar($correctAnswer) ? (string) $correctAnswer : json_encode($correctAnswer);
        $student = filled($userAnswer) ? (string) $userAnswer : '(no answer given)';

        $prompt = <<<PROMPT
{$context}

A radiography student answered an OET question. Explain the result clearly.

QUESTION:
"""
{$question}
"""
CORRECT ANSWER: {$correct}
STUDENT'S ANSWER: {$student}

Return ONLY valid JSON:
{
  "verdict": "correct" | "incorrect" | "partially correct",
  "why_correct": "explanation of the correct answer",
  "why_correct_my": "the same explanation in natural Burmese (မြန်မာစာ)",
  "why_student": "explanation of why the student's answer is right or wrong",
  "why_student_my": "the same explanation in natural Burmese (မြန်မာစာ)",
  "key_concept": "the underlying clinical/language concept to remember",
  "key_concept_my": "the same concept in natural Burmese (မြန်မာစာ)",
  "study_tip": "one actionable tip for next time",
  "study_tip_my": "the same tip in natural Burmese (မြန်မာစာ)"
}
JSON only.
PROMPT;

        try {
            $result = $this->generateWithMeta($prompt, [
                'feature' => 'explain_answer',
                'userId'  => $userId,
                'json'    => true,
            ]);

            return array_merge(['ok' => true, 'source' => $result['provider']], (array) $result['data']);
        } catch (Throwable $e) {
            return [
                'ok'          => true,
                'source'      => 'heuristic',
                'verdict'     => mb_strtolower(trim((string) $userAnswer)) === mb_strtolower(trim($correct)) ? 'correct' : 'incorrect',
                'why_correct' => $correct,
                'why_student' => 'AI explanation unavailable — compare your answer with the correct answer above.',
                'key_concept' => '',
                'study_tip'   => 'Review the relevant radiography vocabulary and re-read the question stem carefully.',
                'error'       => $e->getMessage(),
            ];
        }
    }

    /** Generate a fresh full mock exam blueprint. */
    public function generateMockExam(array $focusAreas = [], ?int $userId = null): array
    {
        $context = self::RADIOGRAPHY_CONTEXT;
        $focus = ! empty($focusAreas) ? implode(', ', $focusAreas) : 'all four sub-tests';

        $prompt = <<<PROMPT
{$context}

Create a blueprint for a full OET mock exam for radiographers.
Focus areas requested: {$focus}.

Return ONLY valid JSON:
{
  "title": "short exam title",
  "description": "1-2 sentence description",
  "duration_minutes": 180,
  "sections": [
    { "section": "Listening", "summary": "...", "question_count": 12 },
    { "section": "Reading",   "summary": "...", "question_count": 12 },
    { "section": "Writing",   "summary": "...", "question_count": 1  },
    { "section": "Speaking",  "summary": "...", "question_count": 2  }
  ]
}
JSON only.
PROMPT;

        $result = $this->generateWithMeta($prompt, [
            'feature' => 'generate_mock_exam',
            'userId'  => $userId,
            'json'    => true,
        ]);

        return array_merge(['ok' => true, 'source' => $result['provider']], (array) $result['data']);
    }

    // -----------------------------------------------------------------------
    // Tutorials / lessons (bilingual EN + MY)
    // -----------------------------------------------------------------------

    /** Draft a complete bilingual tutorial lesson. */
    public function generateLesson(
        string $topic,
        string $category = 'ExamStrategy',
        string $level = 'beginner',
        string $notes = '',
        ?int $userId = null,
        string $track = 'Radiography'
    ): array {
        $context = self::TRACK_CONTEXTS[$track] ?? self::RADIOGRAPHY_CONTEXT;
        $audience = self::TRACK_AUDIENCES[$track] ?? self::TRACK_AUDIENCES['Radiography'];
        $rules = self::BILINGUAL_RULES;
        $extra = $notes !== '' ? "EXTRA GUIDANCE FROM THE EDITOR:\n{$notes}" : '';

        $prompt = <<<PROMPT
{$context}

TASK: Write a complete self-study tutorial lesson for {$audience}.

TOPIC: {$topic}
CATEGORY: {$category}
LEVEL: {$level}
{$extra}

{$rules}

The lesson must be genuinely teachable and COMPLETE — never a summary, an
outline, or a list of bullet points standing in for an explanation. Write at
least 700 words of English content (roughly 4,000 characters) and give the
Burmese version the same depth, structure and worked detail — do not abridge it.

Every lesson must contain, in this order:
1. An introduction that frames the specific problem the candidate faces.
2. The strategy itself, explained step by step, with the reasoning made explicit.
3. At least one worked example — show the material, then show the thinking that
   leads to the answer, not just the answer.
4. The common mistakes candidates make, and how to avoid each one.
5. A short practice task, with the answer or a model response.

Return ONLY valid JSON with EXACTLY these keys:
{
  "title": "English lesson title",
  "title_my": "the same title in Burmese",
  "summary": "2 sentence English summary shown in the lesson list",
  "summary_my": "the same summary in Burmese",
  "content": "the full English lesson body, Markdown, using ## headings",
  "content_my": "the full Burmese lesson body, Markdown, same structure and depth",
  "duration_minutes": 10,
  "key_points": ["short English takeaway", "..."],
  "key_points_my": ["the same takeaways in Burmese", "..."]
}
JSON only — no commentary outside the object.
PROMPT;

        $result = $this->generateWithMeta($prompt, [
            'feature' => 'generate_lesson',
            'userId'  => $userId,
            'json'    => true,
        ]);

        $data = (array) $result['data'];

        return [
            'ok'               => true,
            'source'           => $result['provider'],
            'title'            => $data['title'] ?? $topic,
            'title_my'         => $data['title_my'] ?? '',
            'summary'          => $data['summary'] ?? '',
            'summary_my'       => $data['summary_my'] ?? '',
            'content'          => $data['content'] ?? '',
            'content_my'       => $data['content_my'] ?? '',
            'category'         => $category,
            'track'            => $track,
            'level'            => $level,
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 10),
            'key_points'       => is_array($data['key_points'] ?? null) ? $data['key_points'] : [],
            'key_points_my'    => is_array($data['key_points_my'] ?? null) ? $data['key_points_my'] : [],
        ];
    }

    /**
     * Translate a batch of English strings into natural Burmese.
     *
     * Batching many strings into a single request is what keeps bulk backfill
     * inside the free-tier request quota — never call this one row at a time.
     *
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    public function translateToMyanmar(
        array $payload,
        ?int $userId = null,
        string $feature = 'translate_my',
        string $track = 'Radiography'
    ): array {
        $entries = array_filter($payload, fn ($v) => is_string($v) && trim($v) !== '');
        if (empty($entries)) {
            return [];
        }

        /*
         * The translation persona has to follow the track, not just the body
         * text. A General lesson translated by a "radiographer" persona comes
         * back with imaging vocabulary and an instruction to keep X-ray/CT/MRI
         * in English — which is exactly the drift the General track exists to
         * prevent. The abbreviation list is the sneakiest part of that: it is a
         * positive instruction, so the model follows it even when the source
         * text never mentioned imaging.
         */
        $audience = self::TRACK_AUDIENCES[$track] ?? self::TRACK_AUDIENCES['Radiography'];
        $abbreviations = $track === 'General'
            ? 'OET, BP, ECG, IV, GP, MDT, OTC'
            : 'OET, X-ray, CT, MRI, ALARA, PACS';

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
You are a professional English → Burmese (မြန်မာစာ) translator working on an
OET exam-preparation platform for {$audience}.

Translate the VALUE of every key below into natural, fluent Burmese as a teacher
would write it for those students. Rules:
- Preserve Markdown formatting exactly (##, **, -, numbered lists, tables, line breaks).
- Keep established clinical abbreviations in English: {$abbreviations}.
- Do not introduce terminology from a healthcare profession other than the
  student's own.
- On first use of a clinical term, give the Burmese then the English in parentheses.
- Translate the meaning, never transliterate word-by-word.
- Return ONLY valid JSON with the SAME keys and translated values. No extra keys.

INPUT JSON:
{$json}
PROMPT;

        $result = $this->generateWithMeta($prompt, [
            'feature' => $feature,
            'userId'  => $userId,
            'json'    => true,
        ]);

        // Only trust keys we actually asked for.
        $out = [];
        foreach (array_keys($entries) as $key) {
            $value = $result['data'][$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
