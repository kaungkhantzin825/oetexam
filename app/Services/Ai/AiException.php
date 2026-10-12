<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Carries a classification alongside the message so the provider chain can
 * decide whether to retry, switch model, or switch provider.
 *
 *   truncated    — output hit the token ceiling (config problem, do not retry)
 *   daily_quota  — per-day allowance gone (retrying is pointless, next model)
 *   rate_limit   — per-minute throttle (retry with backoff)
 *   transient    — 5xx / network (retry with backoff)
 *   timeout      — the request's wall-clock budget ran out (do not retry)
 *   fatal        — bad key, bad request, unknown (do not retry)
 */
class AiException extends RuntimeException
{
    public const TRUNCATED   = 'truncated';
    public const DAILY_QUOTA = 'daily_quota';
    public const RATE_LIMIT  = 'rate_limit';
    public const TRANSIENT   = 'transient';
    public const TIMEOUT     = 'timeout';
    public const FATAL       = 'fatal';

    public string $kind;

    /** Provider-specific code (e.g. Mistral's 1300 / 1910). */
    public ?string $providerCode;

    public ?int $status;

    public function __construct(
        string $message,
        string $kind = self::FATAL,
        ?string $providerCode = null,
        ?int $status = null
    ) {
        parent::__construct($message);
        $this->kind = $kind;
        $this->providerCode = $providerCode;
        $this->status = $status;
    }

    /**
     * The shared wall-clock budget for this request is spent. Deliberately NOT
     * retryable — there is no time left to spend, and retrying is what let the
     * chain outlive the request in the first place.
     *
     * @param  list<string>  $failures  "model:kind" entries tried so far
     */
    public static function timeBudgetExceeded(float $seconds, array $failures = []): self
    {
        $tried = $failures === [] ? 'no model produced an answer' : 'tried: '.implode(', ', $failures);

        return new self(
            sprintf('AI request exceeded its %.0fs time budget (%s).', $seconds, $tried),
            self::TIMEOUT,
            'AI_TIME_BUDGET_EXCEEDED',
            504
        );
    }

    public function isRetryable(): bool
    {
        return $this->kind === self::RATE_LIMIT || $this->kind === self::TRANSIENT;
    }

    /** True when the *provider* is out of allowance rather than merely throttled. */
    public function isQuotaExhausted(): bool
    {
        return $this->kind === self::DAILY_QUOTA;
    }
}
