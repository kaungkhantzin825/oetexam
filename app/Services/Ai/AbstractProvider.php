<?php

namespace App\Services\Ai;

use App\Models\AiLog;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared behaviour for the concrete providers: the retry loop over an ordered
 * model chain, quota accounting and the `ai_logs` audit trail.
 */
abstract class AbstractProvider implements Provider
{
    protected int $maxRetries;
    protected int $retryBaseMs;

    /** Shared across every provider in one AI request. */
    protected ?Budget $budget = null;

    public function __construct()
    {
        $this->maxRetries = (int) config('ai.max_retries', 1);
        $this->retryBaseMs = (int) config('ai.retry_base_ms', 800);
    }

    protected function aiEnabled(): bool
    {
        return (bool) config('ai.enabled', true);
    }

    /** The provider's own per-call ceiling, before the budget clamps it. */
    abstract protected function preferredTimeout(): float;

    /**
     * Text-only by default.
     *
     * Opting IN is the safe default: a provider that has not been checked for
     * image support must not be handed a photographed answer, because it would
     * mark the prompt and return a confident, wrong result. Gemini overrides
     * this to true.
     */
    public function supportsVision(): bool
    {
        return false;
    }

    /**
     * Run the chain: for each model, retry transient failures with exponential
     * backoff; on a daily-quota exhaustion move straight to the next model.
     *
     * Every attempt is bounded by a shared wall-clock budget, so the chain can
     * never outlive the request. See Budget for why that matters.
     *
     * @param  callable(string, float): string  $call  (model, timeout) -> completion
     * @return array{data: mixed, model: string}
     */
    protected function runChain(string $prompt, array $opts, callable $call): array
    {
        $json = (bool) ($opts['json'] ?? false);
        $feature = $opts['feature'] ?? 'generic';
        $userId = $opts['userId'] ?? null;

        if (! $this->isConfigured()) {
            throw new AiException(
                $this->name().' is not configured',
                AiException::FATAL,
                'AI_NOT_CONFIGURED'
            );
        }

        // One budget for the whole request — handed down by AiManager so the
        // Mistral fallback inherits whatever Gemini did not spend.
        $this->budget = $opts['budget'] ?? Budget::fromConfig();
        $budget = $this->budget;

        $started = microtime(true);
        $text = null;
        $lastError = null;
        $failures = [];
        $outOfTime = false;

        foreach ($this->modelChain() as $model) {
            $retriesLeft = $this->maxRetries;

            while (true) {
                // Fail fast rather than start a call that cannot finish.
                if ($budget->exhausted()) {
                    $outOfTime = true;
                    break 2;
                }

                try {
                    $text = $call($model, $budget->slice($this->preferredTimeout()));
                    $data = $json ? Json::parseLoose($text) : $text;
                    $latency = (int) ((microtime(true) - $started) * 1000);

                    $this->log([
                        'user_id'    => $userId,
                        'feature'    => $feature,
                        'model'      => $this->name().':'.$model,
                        'prompt'     => $prompt,
                        'response'   => $text,
                        'latency_ms' => $latency,
                        'success'    => true,
                    ]);

                    return ['data' => $data, 'model' => $model];
                } catch (AiException $e) {
                    $lastError = $e;
                    $failures[] = $model.':'.$e->kind;

                    if ($e->isRetryable() && $retriesLeft > 0 && ! $budget->exhausted()) {
                        $used = $this->maxRetries - $retriesLeft;
                        $retriesLeft--;
                        $budget->sleep($this->retryBaseMs * (2 ** $used) / 1000);
                        continue;
                    }
                    break;
                }
            }
        }

        $latency = (int) ((microtime(true) - $started) * 1000);

        // Running out of time is the headline failure — but only when we did not
        // already establish something more specific (e.g. every model is out of
        // daily quota, which no amount of time would fix).
        $allQuota = $failures !== [] && count(array_filter($failures, fn ($f) => str_ends_with($f, ':'.AiException::DAILY_QUOTA))) === count($failures);

        if ($outOfTime && ! $allQuota) {
            $lastError = AiException::timeBudgetExceeded($budget->seconds(), $failures);
        }

        if ($allQuota && $lastError) {
            $lastError = new AiException(
                sprintf(
                    '%s quota is exhausted for every configured model (%s).',
                    ucfirst($this->name()),
                    implode(', ', $this->modelChain())
                ),
                AiException::DAILY_QUOTA,
                $lastError->providerCode,
                429
            );
        }

        $this->log([
            'user_id'    => $userId,
            'feature'    => $feature,
            'model'      => $this->name().':'.($this->modelChain()[0] ?? 'unknown'),
            'prompt'     => $prompt,
            'response'   => $text,
            'latency_ms' => $latency,
            'success'    => false,
            'error'      => substr(($lastError?->getMessage() ?? '').' | tried: '.implode(', ', $failures), 0, 2000),
        ]);

        throw $lastError ?? new AiException($this->name().' failed', AiException::FATAL);
    }

    /** Fire-and-forget audit insert. Never breaks the request path. */
    protected function log(array $attributes): void
    {
        try {
            AiLog::create([
                'user_id'    => $attributes['user_id'] ?? null,
                'feature'    => $attributes['feature'] ?? 'generic',
                'model'      => $attributes['model'] ?? null,
                'prompt'     => mb_substr((string) ($attributes['prompt'] ?? ''), 0, 60000),
                'response'   => mb_substr((string) ($attributes['response'] ?? ''), 0, 60000),
                'latency_ms' => $attributes['latency_ms'] ?? 0,
                'success'    => $attributes['success'] ?? true,
                'error'      => isset($attributes['error']) ? mb_substr((string) $attributes['error'], 0, 2000) : null,
            ]);
        } catch (Throwable) {
            // Logging must never throw.
        }
    }
}
