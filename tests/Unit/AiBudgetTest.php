<?php

namespace Tests\Unit;

use App\Services\Ai\AbstractProvider;
use App\Services\Ai\AiException;
use App\Services\Ai\Budget;
use Tests\TestCase;

/**
 * The provider chain is `providers x models x (1 + retries)`. Before the shared
 * budget existed, that product was unbounded and a single broken AI request
 * burned 97 seconds before PHP killed it with
 * "Maximum execution time of 60 seconds exceeded" — so the caller got a fatal
 * error instead of the classified AiException every AI endpoint is written to
 * return.
 *
 * These tests pin the fix: the chain can never outlive its budget.
 */
class AiBudgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The providers write an audit row per attempt. Point Eloquent at an
        // empty in-memory database so the tests never touch the real ai_logs
        // table — AiLog::create failing is swallowed by AbstractProvider::log().
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function configureBudget(array $overrides = []): void
    {
        config(array_merge([
            'ai.total_budget_seconds' => 3,
            'ai.min_attempt_seconds'  => 1,
            'ai.max_retries'          => 0,
            'ai.retry_base_ms'        => 10,
        ], $overrides));
    }

    public function test_a_long_chain_of_failing_models_is_bounded_by_the_budget(): void
    {
        $this->configureBudget();

        // 8 models x 1.2s = 9.6s if the chain ran unbounded.
        $provider = new FakeProvider(
            models: ['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8'],
            attemptSeconds: 1.2
        );

        $started = microtime(true);
        $caught = null;

        try {
            $provider->run('hello', ['feature' => 'test:budget']);
        } catch (AiException $e) {
            $caught = $e;
        }

        $elapsed = microtime(true) - $started;

        $this->assertNotNull($caught, 'The chain should have failed.');
        $this->assertSame(AiException::TIMEOUT, $caught->kind);
        $this->assertSame(504, $caught->status);
        $this->assertFalse($caught->isRetryable(), 'A spent budget must never be retried.');

        // Budget is 3s; allow one extra in-flight attempt's worth of slack.
        $this->assertLessThan(5.0, $elapsed, "Chain ran for {$elapsed}s — the budget did not bound it.");
    }

    public function test_a_successful_model_short_circuits_the_rest_of_the_chain(): void
    {
        $this->configureBudget(['ai.total_budget_seconds' => 30]);

        $provider = new FakeProvider(
            models: ['m1', 'm2', 'm3'],
            attemptSeconds: 0.05,
            succeedOn: 'm2'
        );

        $started = microtime(true);
        $result = $provider->run('hello', ['json' => true, 'feature' => 'test:success']);
        $elapsed = microtime(true) - $started;

        $this->assertSame('m2', $result['model']);
        $this->assertSame(['ok' => true], $result['data'], 'The JSON option must still parse the completion.');
        $this->assertLessThan(0.5, $elapsed, 'The chain must stop at the first model that answers.');
    }

    public function test_the_whole_provider_chain_shares_one_budget(): void
    {
        $this->configureBudget();

        $budget = Budget::fromConfig();
        $first = new FakeProvider(models: ['a1'], attemptSeconds: 1.5);
        $second = new FakeProvider(models: ['b1', 'b2', 'b3'], attemptSeconds: 1.5);

        $started = microtime(true);

        // Provider 1 burns half the budget, then fails.
        try {
            $first->run('hello', ['budget' => $budget, 'feature' => 'test:shared']);
            $this->fail('Provider 1 was expected to fail.');
        } catch (AiException $e) {
            $this->assertSame(AiException::TRANSIENT, $e->kind);
        }

        // Provider 2 (the fallback) must inherit what is left, not restart the clock.
        try {
            $second->run('hello', ['budget' => $budget, 'feature' => 'test:shared']);
            $this->fail('Provider 2 was expected to run out of budget.');
        } catch (AiException $e) {
            $this->assertSame(AiException::TIMEOUT, $e->kind);
        }

        $elapsed = microtime(true) - $started;
        $this->assertLessThan(5.0, $elapsed, "The fallback restarted the clock ({$elapsed}s).");
    }

    public function test_a_slice_is_clamped_to_the_time_actually_left(): void
    {
        $budget = new Budget(10, 1);

        $this->assertLessThanOrEqual(10, $budget->slice(40), 'A slice may never exceed the remaining budget.');
        $this->assertGreaterThan(9, $budget->slice(40), 'With the full budget left, a long slice is allowed.');
        $this->assertSame(5.0, $budget->slice(5), 'A short preference is honoured as-is.');
    }

    public function test_a_budget_is_exhausted_once_it_drops_below_the_minimum_attempt_window(): void
    {
        $budget = new Budget(1.0, 2.0);

        $this->assertTrue($budget->exhausted(), 'Less time left than one attempt needs means stop.');

        $roomy = new Budget(60, 5);
        $this->assertFalse($roomy->exhausted());
    }

    public function test_retry_backoff_never_sleeps_past_the_budget(): void
    {
        $budget = new Budget(1.0, 0.8);

        $started = microtime(true);
        $budget->sleep(10);
        $elapsed = microtime(true) - $started;

        // Only 0.2s is usable (1.0 - 0.8), so it must not sleep the full 10s.
        $this->assertLessThan(0.6, $elapsed, "Backoff slept {$elapsed}s and overran the budget.");
    }
}

/**
 * A provider with no transport: each attempt takes a fixed amount of time and
 * either fails as a retryable transient error or succeeds for one chosen model.
 */
class FakeProvider extends AbstractProvider
{
    public function __construct(
        private readonly array $models,
        private readonly float $attemptSeconds,
        private readonly ?string $succeedOn = null,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function modelChain(): array
    {
        return $this->models;
    }

    protected function preferredTimeout(): float
    {
        return 40.0;
    }

    public function run(string $prompt, array $opts = []): array
    {
        return $this->runChain($prompt, $opts, function (string $model, float $timeout) {
            // Simulate the round trip. A real provider would be cut off by
            // Http::timeout($timeout) — the budget guarantees that is >= the
            // remaining time, so this never overshoots.
            usleep((int) ($this->attemptSeconds * 1_000_000));

            if ($this->succeedOn === $model) {
                return '{"ok": true}';
            }

            throw new AiException('fake transient failure', AiException::TRANSIENT);
        });
    }
}
