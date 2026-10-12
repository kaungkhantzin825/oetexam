<?php

namespace Tests\Unit;

use App\Services\Ai\AiException;
use App\Services\Ai\GeminiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gemini's wording for a given HTTP status varies, and classification used to
 * read only the message text. A 503 worded as
 *
 *   "This model is currently experiencing high demand. Spikes in demand are
 *    usually temporary. Please try again later."
 *
 * contains none of the words the matcher looked for ("unavailable", "overloaded",
 * "[503]"), so a perfectly retryable server error was downgraded to a
 * non-retryable `fatal`. The chain then abandoned a healthy model and, because
 * every later model inherited the same fate, burned the request's entire time
 * budget. These tests pin the status-code-first rule.
 */
class GeminiProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never touch the real ai_logs table from a test.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'ai.enabled' => true,
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'gemini-3.5-flash',
            'ai.gemini.fallback_models' => [],
            'ai.max_retries' => 0,
            'ai.retry_base_ms' => 1,
            'ai.total_budget_seconds' => 10,
            'ai.min_attempt_seconds' => 1,
        ]);
    }

    /** @return array{0: AiException|null, 1: mixed} */
    private function runExpectingFailure(string $prompt = 'hello'): array
    {
        $provider = app(GeminiProvider::class);

        try {
            return [null, $provider->run($prompt, ['feature' => 'test:gemini'])];
        } catch (AiException $e) {
            return [$e, null];
        }
    }

    public function test_a_503_worded_as_high_demand_is_retryable_not_fatal(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'code' => 503,
                    'message' => 'This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.',
                    'status' => 'UNAVAILABLE',
                ],
            ], 503),
        ]);

        [$error] = $this->runExpectingFailure();

        $this->assertNotNull($error, 'A 503 should raise an AiException.');
        $this->assertSame(AiException::TRANSIENT, $error->kind, 'A 503 must be retryable whatever its wording.');
        $this->assertTrue($error->isRetryable());
        $this->assertSame(503, $error->status);
    }

    public function test_a_500_is_transient(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Internal error']], 500)]);

        [$error] = $this->runExpectingFailure();

        $this->assertSame(AiException::TRANSIENT, $error->kind);
    }

    public function test_a_429_mentioning_per_day_is_a_daily_quota_not_a_rate_limit(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Quota exceeded for quota metric GenerateRequestsPerDayPerProjectPerModel.',
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ], 429),
        ]);

        [$error] = $this->runExpectingFailure();

        $this->assertSame(AiException::DAILY_QUOTA, $error->kind);
        $this->assertFalse($error->isRetryable(), 'A per-day quota must never be retried.');
    }

    public function test_a_429_without_the_per_day_marker_is_a_retryable_rate_limit(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['code' => 429, 'message' => 'Too many requests, slow down.', 'status' => 'RESOURCE_EXHAUSTED'],
            ], 429),
        ]);

        [$error] = $this->runExpectingFailure();

        $this->assertSame(AiException::RATE_LIMIT, $error->kind);
        $this->assertTrue($error->isRetryable());
    }

    public function test_a_400_is_fatal_and_not_retried(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['code' => 400, 'message' => 'Invalid JSON payload received.', 'status' => 'INVALID_ARGUMENT'],
            ], 400),
        ]);

        [$error] = $this->runExpectingFailure();

        $this->assertSame(AiException::FATAL, $error->kind);
        $this->assertFalse($error->isRetryable());
    }

    public function test_a_transient_error_is_retried_and_can_then_succeed(): void
    {
        config(['ai.max_retries' => 1]);

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return $attempts === 1
                ? Http::response(['error' => ['message' => 'This model is currently experiencing high demand.']], 503)
                : Http::response([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => '{"ok":true}']]],
                        'finishReason' => 'STOP',
                    ]],
                ], 200);
        });

        $provider = app(GeminiProvider::class);
        $result = $provider->run('hello', ['json' => true, 'feature' => 'test:gemini']);

        $this->assertSame(2, $attempts, 'The transient failure should have been retried once.');
        $this->assertSame(['ok' => true], $result['data']);
    }

    public function test_an_empty_candidate_says_why_it_was_empty(): void
    {
        Http::fake([
            '*' => Http::response([
                'promptFeedback' => ['blockReason' => 'SAFETY'],
                'candidates' => [['finishReason' => 'SAFETY', 'index' => 0]],
            ], 200),
        ]);

        [$error] = $this->runExpectingFailure();

        $this->assertSame(AiException::FATAL, $error->kind);
        $this->assertStringContainsString('empty completion', $error->getMessage());
        $this->assertStringContainsString('SAFETY', $error->getMessage(), 'The reason must be reported, not swallowed.');
    }

    public function test_a_max_tokens_finish_is_reported_as_truncated(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"partial":']]],
                    'finishReason' => 'MAX_TOKENS',
                ]],
            ], 200),
        ]);

        [$error] = $this->runExpectingFailure();

        $this->assertSame(AiException::TRUNCATED, $error->kind);
    }
}
