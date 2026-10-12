<?php

namespace App\Services\Ai;

/**
 * One wall-clock budget shared by every provider, every model and every retry
 * inside a single AI request.
 *
 * Why this exists: the chain is `providers x models x (1 + retries)`. With a
 * 90s per-call timeout and four models that is a worst case measured in
 * *minutes*, which sails past PHP's execution limit — the request is killed
 * mid-flight and the caller gets a fatal error instead of the classified
 * AiException it is written to handle (the live symptom was
 * "Maximum execution time of 60 seconds exceeded" from Guzzle's CurlHandler).
 *
 * The fix is to stop treating each call as independent: every HTTP call is
 * capped by whatever is left of the budget, and no new attempt starts with
 * less than `minAttemptSeconds` on the clock. The total worst case becomes
 * ~`seconds` no matter how many models are configured, and failures surface as
 * a clean, classified 504 rather than a fatal.
 */
class Budget
{
    private float $deadline;

    public function __construct(
        private readonly float $seconds,
        private readonly float $minAttemptSeconds = 5.0
    ) {
        $this->deadline = microtime(true) + $seconds;
    }

    /** Build from `config/ai.php`, with sane floors. */
    public static function fromConfig(): self
    {
        return new self(
            max(1.0, (float) config('ai.total_budget_seconds', 45)),
            max(1.0, (float) config('ai.min_attempt_seconds', 5))
        );
    }

    /** Seconds left before the deadline (may be negative once spent). */
    public function remaining(): float
    {
        return $this->deadline - microtime(true);
    }

    /** True when there is not enough time left to justify another attempt. */
    public function exhausted(): bool
    {
        return $this->remaining() < $this->minAttemptSeconds;
    }

    /**
     * How long a single HTTP call may take: the provider's own preference,
     * clamped to the time actually left. This is what bounds the whole request.
     */
    public function slice(float $preferred): float
    {
        return max(1.0, min($preferred, $this->remaining()));
    }

    /** The configured total, for error messages. */
    public function seconds(): float
    {
        return $this->seconds;
    }

    /**
     * Back off between retries — but never sleep past the point where another
     * attempt could still finish.
     */
    public function sleep(float $seconds): void
    {
        $usable = $this->remaining() - $this->minAttemptSeconds;

        if ($usable <= 0) {
            return;
        }

        usleep((int) (min($seconds, $usable) * 1_000_000));
    }
}
