<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Gemini provider.
 *
 * Talks to the REST API directly (the Node version used the JS SDK, but the
 * wire format is identical) so the only extra dependency is Guzzle, which
 * Laravel already ships with.
 */
class GeminiProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return $this->aiEnabled() && trim((string) config('ai.gemini.key')) !== '';
    }

    public function modelChain(): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [(string) config('ai.gemini.model')],
            (array) config('ai.gemini.fallback_models')
        ))));
    }

    protected function preferredTimeout(): float
    {
        return (float) config('ai.gemini.timeout', 40);
    }

    /** Gemini reads images natively via `inlineData` parts. */
    public function supportsVision(): bool
    {
        return true;
    }

    public function run(string $prompt, array $opts = []): array
    {
        $json = (bool) ($opts['json'] ?? false);
        $attachments = $this->normaliseAttachments($opts['attachments'] ?? []);

        return $this->runChain(
            $prompt,
            $opts,
            fn (string $model, float $timeout) => $this->callModel($model, $prompt, $json, $timeout, $attachments)
        );
    }

    /**
     * Keep only well-formed attachments — a malformed entry would otherwise
     * produce a 400 from Google that reads like a prompt problem.
     *
     * @return array<int, array{mime: string, data: string}>
     */
    private function normaliseAttachments(mixed $attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        $clean = [];
        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $mime = trim((string) ($attachment['mime'] ?? ''));
            $data = (string) ($attachment['data'] ?? '');
            if ($mime === '' || $data === '') {
                continue;
            }
            $clean[] = ['mime' => $mime, 'data' => $data];
        }

        return $clean;
    }

    /**
     * Gemini 2.5+ "thinking" models spend part of `maxOutputTokens` on hidden
     * reasoning before writing any visible output, which truncates long
     * structured responses mid-JSON. Flash-class models accept
     * `thinkingBudget: 0`; pro-class models reject it and keep the default.
     */
    private function canDisableThinking(string $model): bool
    {
        return preg_match('/flash|flash-lite/i', $model) === 1
            && preg_match('/^gemini-(2\.5|3)/i', $model) === 1;
    }

    /**
     * @param  float  $timeout  seconds — already clamped to the request budget
     * @param  array<int, array{mime: string, data: string}>  $attachments  base64 inline data
     */
    private function callModel(string $model, string $prompt, bool $json, float $timeout, array $attachments = []): string
    {
        $key = (string) config('ai.gemini.key');
        $url = rtrim((string) config('ai.gemini.base'), '/')."/v1beta/models/{$model}:generateContent";

        $generationConfig = [
            'temperature'     => $json ? 0.4 : 0.7,
            'topP'            => 0.95,
            'maxOutputTokens' => (int) config('ai.gemini.max_output_tokens'),
        ];

        if ($json) {
            // Native JSON mode — the response is well-formed, no fences to strip.
            $generationConfig['responseMimeType'] = 'application/json';
            if ($this->canDisableThinking($model)) {
                $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
            }
        }

        // `floor` so a fractional budget can never round *up* past the deadline.
        $seconds = max(1, (int) floor($timeout));

        // The text prompt always comes first; images follow as inline parts.
        // Sending an image alongside a JSON-mode request is what lets the model
        // transcribe the handwriting and mark it in a single call.
        $parts = [['text' => $prompt]];
        foreach ($attachments as $attachment) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $attachment['mime'],
                    'data'     => $attachment['data'],
                ],
            ];
        }

        try {
            $response = Http::timeout($seconds)
                ->connectTimeout(min(10, $seconds))
                ->acceptJson()
                ->asJson()
                ->post($url.'?key='.urlencode($key), [
                    'contents'         => [['parts' => $parts]],
                    'generationConfig' => $generationConfig,
                ]);
        } catch (Throwable $e) {
            // A Guzzle timeout is a retryable transport failure; anything else
            // (DNS, TLS, abort) is classified the same way and retried, bounded
            // by the budget.
            throw new AiException(
                'Gemini request failed: '.$e->getMessage(),
                AiException::TRANSIENT,
                null,
                0
            );
        }

        $raw = $response->body();

        if (! $response->successful()) {
            $message = mb_substr($raw, 0, 300);
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['error']['message'])) {
                $message = $decoded['error']['message'];
            }

            throw new AiException(
                sprintf('Gemini %d: %s', $response->status(), $message),
                $this->classify($message, $response->status()),
                null,
                $response->status()
            );
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            throw new AiException('Gemini returned a non-JSON HTTP body.', AiException::FATAL);
        }

        $candidate = $payload['candidates'][0] ?? null;
        $finishReason = $candidate['finishReason'] ?? null;

        // A truncated response can never be valid JSON — fail loudly rather than
        // letting a partial object reach the student.
        if ($finishReason === 'MAX_TOKENS') {
            throw new AiException(
                'Gemini response was truncated (MAX_TOKENS). Raise GEMINI_MAX_OUTPUT_TOKENS or shorten the prompt.',
                AiException::TRUNCATED
            );
        }

        $parts = $candidate['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            $text .= $part['text'] ?? '';
        }

        if (trim($text) === '') {
            // An empty candidate is never self-explanatory: the prompt may have
            // been blocked, the model may have stopped on a safety filter, or
            // the payload may simply be shaped differently. Say which.
            $blocked = $payload['promptFeedback']['blockReason'] ?? null;
            $reason = $blocked
                ?? ($finishReason !== null && $finishReason !== 'STOP' ? $finishReason : null)
                ?? 'no content parts in the response';

            throw new AiException(
                sprintf('Gemini returned an empty completion (%s).', $reason),
                AiException::FATAL
            );
        }

        return $text;
    }

    /**
     * Classify a Gemini failure into the shared vocabulary.
     *
     * The HTTP status is authoritative for throttling and server errors. Gemini
     * words its 503s in several ways — "This model is currently experiencing
     * high demand... Please try again later" does not contain the word
     * "unavailable" — and a message-only match silently downgraded those to a
     * non-retryable `fatal`, so the chain abandoned a perfectly good model and
     * burned the request's whole time budget.
     */
    private function classify(string $message, ?int $status): string
    {
        // Daily quota first: it also arrives as a 429, so the text has to win.
        // The quotaId contains "PerDay" and the retry hint is hours away.
        if (preg_match('/PerDay|per day|RequestsPerDay/i', $message)) {
            return AiException::DAILY_QUOTA;
        }
        if (preg_match('/retry in\s*[\d.]+\s*h/i', $message)) {
            return AiException::DAILY_QUOTA;
        }

        if ($status === 429) {
            return AiException::RATE_LIMIT;
        }
        if ($status !== null && $status >= 500) {
            return AiException::TRANSIENT;
        }

        if (preg_match('/RESOURCE_EXHAUSTED|rate limit|quota/i', $message)) {
            return AiException::RATE_LIMIT;
        }
        if (preg_match('/overloaded|unavailable|deadline|high demand|try again later/i', $message)) {
            return AiException::TRANSIENT;
        }

        return AiException::FATAL;
    }
}
