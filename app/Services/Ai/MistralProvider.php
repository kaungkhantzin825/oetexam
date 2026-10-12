<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mistral AI provider — the automatic fallback when Gemini errors out or its
 * free-tier daily quota runs dry.
 *
 * Mistral's API is OpenAI-compatible, so this is a plain JSON POST.
 */
class MistralProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'mistral';
    }

    public function isConfigured(): bool
    {
        return $this->aiEnabled() && trim((string) config('ai.mistral.key')) !== '';
    }

    /**
     * No. The models this key can reach are text-only (the vision-capable
     * `pixtral-*` family is not served to this tier), so the manager keeps
     * vision requests away from Mistral entirely rather than letting it answer
     * with the prompt and no image.
     */
    public function supportsVision(): bool
    {
        return false;
    }

    public function modelChain(): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [(string) config('ai.mistral.model')],
            (array) config('ai.mistral.fallback_models')
        ))));
    }

    protected function preferredTimeout(): float
    {
        return (float) config('ai.mistral.timeout', 40);
    }

    public function run(string $prompt, array $opts = []): array
    {
        $json = (bool) ($opts['json'] ?? false);

        return $this->runChain(
            $prompt,
            $opts,
            fn (string $model, float $timeout) => $this->callModel($model, $prompt, $json, $timeout)
        );
    }

    /**
     * @param  float  $timeout  seconds — already clamped to the request budget
     */
    private function callModel(string $model, string $prompt, bool $json, float $timeout): string
    {
        $key = (string) config('ai.mistral.key');
        $url = rtrim((string) config('ai.mistral.base'), '/').'/chat/completions';

        $body = [
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => $json ? 0.4 : 0.7,
            'top_p'       => 0.95,
            'max_tokens'  => (int) config('ai.mistral.max_output_tokens'),
        ];

        // Mistral honours OpenAI's JSON mode. The prompt always spells out the
        // required shape, which is what json_object mode requires.
        if ($json) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        // `floor` so a fractional budget can never round *up* past the deadline.
        $seconds = max(1, (int) floor($timeout));

        try {
            $response = Http::timeout($seconds)
                ->connectTimeout(min(10, $seconds))
                ->withToken($key)
                ->acceptJson()
                ->asJson()
                ->post($url, $body);
        } catch (Throwable $e) {
            // Abort / DNS / TLS problems all land here.
            throw new AiException('Mistral request failed: '.$e->getMessage(), AiException::TRANSIENT, null, 0);
        }

        $raw = $response->body();

        if (! $response->successful()) {
            $message = mb_substr($raw, 0, 300);
            $code = null;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $message = $decoded['message'] ?? $decoded['detail'] ?? $message;
                $code = isset($decoded['code']) ? (string) $decoded['code'] : null;
            }

            throw new AiException(
                sprintf('Mistral %d: %s', $response->status(), $message),
                $this->classify($message, $response->status(), $code),
                $code,
                $response->status()
            );
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            throw new AiException('Mistral returned a non-JSON HTTP body.', AiException::FATAL);
        }

        $choice = $payload['choices'][0] ?? null;
        $content = $choice['message']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new AiException('Mistral returned an empty completion.', AiException::FATAL);
        }

        // `finish_reason: 'length'` means the token budget ran out mid-answer.
        if (($choice['finish_reason'] ?? null) === 'length') {
            throw new AiException(
                'Mistral response was truncated (finish_reason: length). Raise MISTRAL_MAX_OUTPUT_TOKENS or shorten the prompt.',
                AiException::TRUNCATED
            );
        }

        return $content;
    }

    /** Classify using the same vocabulary as the Gemini provider. */
    private function classify(string $message, ?int $status, ?string $code): string
    {
        // Mistral signals "out of allowance" with 429 too, so check the text.
        if (preg_match('/monthly|token limit|quota exceeded|insufficient/i', $message)) {
            return AiException::DAILY_QUOTA;
        }
        if ($status === 429 || $code === '1300') {
            return AiException::RATE_LIMIT;
        }
        if ($status === 403 || $code === '1910' || preg_match('/not available in your subscription/i', $message)) {
            return AiException::FATAL; // tier not allowed — try the next model, never retry
        }
        if ($status === 401 || $status === 403) {
            return AiException::FATAL;
        }
        if ($status !== null && $status >= 500) {
            return AiException::TRANSIENT;
        }
        if ($status === 0) {
            return AiException::TRANSIENT; // network / timeout
        }

        return AiException::FATAL;
    }

    /**
     * Cheap liveness check used by the admin AI-status panel.
     *
     * @return array{ok: bool, reason: string|null}
     */
    public function ping(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'reason' => 'not_configured'];
        }

        try {
            $res = Http::timeout(15)
                ->withToken((string) config('ai.mistral.key'))
                ->get(rtrim((string) config('ai.mistral.base'), '/').'/models');

            return ['ok' => $res->successful(), 'reason' => $res->successful() ? null : 'HTTP '.$res->status()];
        } catch (Throwable $e) {
            return ['ok' => false, 'reason' => $e->getMessage()];
        }
    }
}
