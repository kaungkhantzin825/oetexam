<?php

namespace Tests\Unit;

use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\MistralProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Reading a photographed hand-written letter is the only AI feature that needs
 * to see an image. Two things must hold, and both fail silently if they don't:
 *
 *   1. A vision request must never reach a text-only provider. Mistral would
 *      happily answer the prompt without the picture, producing a confident
 *      mark for a letter nobody read — worse than an error.
 *   2. The image must actually be attached. If the inline part were dropped,
 *      Gemini would mark the task prompt as if it were the candidate's letter.
 */
class AiVisionRoutingTest extends TestCase
{
    /** A real file is needed: the manager reads it off disk and base64s it. */
    private ?string $imagePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Never touch the real ai_logs table from a test.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'ai.enabled' => true,
            'ai.gemini.key' => 'test-gemini-key',
            'ai.gemini.model' => 'gemini-3.5-flash',
            'ai.gemini.fallback_models' => [],
            'ai.mistral.key' => 'test-mistral-key',
            'ai.mistral.model' => 'open-mistral-nemo',
            'ai.mistral.fallback_models' => [],
            'ai.max_retries' => 0,
            'ai.retry_base_ms' => 1,
            'ai.total_budget_seconds' => 10,
            'ai.min_attempt_seconds' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->imagePath !== null && is_file($this->imagePath)) {
            @unlink($this->imagePath);
        }

        parent::tearDown();
    }

    /** Write a tiny but genuinely valid PNG and return its upload-relative path. */
    private function makeImage(string $name = 'test-answer.png'): string
    {
        $directory = public_path('uploads/writing/_test');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        // 1x1 PNG — mime_content_type() must recognise it as image/png.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $this->imagePath = $directory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($this->imagePath, $png);

        return 'writing/_test/'.$name;
    }

    /** A well-formed Gemini success body wrapping the given JSON string. */
    private function geminiSuccess(string $json): array
    {
        return [
            'candidates' => [[
                'finishReason' => 'STOP',
                'content'      => ['parts' => [['text' => $json]]],
            ]],
        ];
    }

    // -----------------------------------------------------------------------
    // Capability flags
    // -----------------------------------------------------------------------

    public function test_only_gemini_declares_vision_support(): void
    {
        $this->assertTrue(app(GeminiProvider::class)->supportsVision());
        $this->assertFalse(app(MistralProvider::class)->supportsVision());
    }

    // -----------------------------------------------------------------------
    // Routing
    // -----------------------------------------------------------------------

    public function test_a_vision_request_with_no_vision_provider_reports_vision_unsupported(): void
    {
        // Only the fallback is configured — it cannot see the photo.
        config(['ai.gemini.key' => '']);

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{}']]]])]);

        $caught = null;

        try {
            app(AiManager::class)->generateWithMeta('read this', [
                'feature'     => 'test:vision',
                'json'        => true,
                'attachments' => [['mime' => 'image/png', 'data' => 'AAAA']],
            ]);
        } catch (AiException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame('VISION_UNSUPPORTED', $caught->providerCode);

        // The text-only provider must not have been asked to try.
        Http::assertNothingSent();
    }

    public function test_a_vision_request_never_falls_through_to_the_text_only_provider(): void
    {
        Http::fake([
            // Gemini is momentarily broken.
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'boom']], 500),
            // Mistral would answer happily — and wrongly.
            'api.mistral.ai/*' => Http::response(['choices' => [['message' => ['content' => '{"band_score":6}']]]]),
        ]);

        $caught = null;

        try {
            app(AiManager::class)->generateWithMeta('read this', [
                'feature'     => 'test:vision',
                'json'        => true,
                'attachments' => [['mime' => 'image/png', 'data' => 'AAAA']],
            ]);
        } catch (AiException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'The chain should fail rather than mark an unread photo.');

        $hosts = array_map(
            fn ($request) => parse_url($request->url(), PHP_URL_HOST),
            Http::recorded()->map(fn ($pair) => $pair[0])->all()
        );

        $this->assertContains('generativelanguage.googleapis.com', $hosts);
        $this->assertNotContains('api.mistral.ai', $hosts, 'Mistral must never receive a vision request.');
    }

    public function test_a_text_only_request_still_uses_the_mistral_fallback(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'boom']], 500),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => '{"ok":true}']]],
            ]),
        ]);

        $result = app(AiManager::class)->generateWithMeta('plain text please', [
            'feature' => 'test:text',
            'json'    => true,
        ]);

        $this->assertSame('mistral', $result['provider']);
        $this->assertTrue($result['fallbackUsed']);
    }

    // -----------------------------------------------------------------------
    // The attachment actually reaches the model
    // -----------------------------------------------------------------------

    public function test_the_image_is_sent_as_an_inline_part(): void
    {
        $relative = $this->makeImage();

        Http::fake([
            '*' => Http::response($this->geminiSuccess('{"ok":true,"transcript":"hi"}')),
        ]);

        app(AiManager::class)->scoreWritingFromImage('Write a referral letter.', $relative);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $parts = $body['contents'][0]['parts'] ?? [];

            // The examiner prompt is composed by the facade, so assert on the
            // task being carried through rather than on an exact string.
            $this->assertStringContainsString('Write a referral letter.', $parts[0]['text'] ?? '');
            $this->assertStringContainsString('Transcribe the handwriting', $parts[0]['text'] ?? '');

            $inline = $parts[1]['inlineData'] ?? null;
            $this->assertNotNull($inline, 'The image must ride along as an inlineData part.');
            $this->assertSame('image/png', $inline['mimeType']);

            // base64 of the 1x1 PNG we wrote.
            $this->assertSame(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                $inline['data']
            );

            // A photo is useless without JSON mode, which also disables thinking
            // on flash-class models so the long transcript is not truncated.
            $this->assertSame('application/json', $body['generationConfig']['responseMimeType'] ?? null);

            return true;
        });
    }

    // -----------------------------------------------------------------------
    // scoreWritingFromImage
    // -----------------------------------------------------------------------

    public function test_a_successful_read_returns_the_transcript_and_the_marks(): void
    {
        $relative = $this->makeImage();

        Http::fake([
            '*' => Http::response($this->geminiSuccess(json_encode([
                'legible'                => true,
                'handwriting_confidence' => 'high',
                'transcript'             => "Dear Dr Lee,\n\nThank you for referring Mr Tan.",
                'criteria'               => ['purpose' => ['score' => 3, 'max' => 3, 'comment' => 'Clear.']],
                'band_score'             => 5.5,
                'strengths'              => ['Well organised.'],
            ]))),
        ]);

        $result = app(AiManager::class)->scoreWritingFromImage('Write a referral letter.', $relative);

        $this->assertTrue($result['ok']);
        $this->assertSame('gemini', $result['source']);
        $this->assertSame(5.5, (float) $result['band_score']);
        $this->assertStringContainsString('Dear Dr Lee', (string) $result['transcript']);
        $this->assertSame('high', $result['handwriting_confidence']);
    }

    public function test_a_missing_image_file_fails_without_calling_any_provider(): void
    {
        Http::fake(['*' => Http::response($this->geminiSuccess('{"band_score":6}'))]);

        $result = app(AiManager::class)->scoreWritingFromImage('task', 'writing/999/does-not-exist.jpg');

        $this->assertFalse($result['ok']);
        $this->assertSame(0.0, (float) $result['band_score']);
        $this->assertNull($result['transcript']);
        $this->assertNotEmpty($result['notice']);
        Http::assertNothingSent();
    }

    public function test_a_path_traversal_attempt_is_refused(): void
    {
        Http::fake(['*' => Http::response($this->geminiSuccess('{"band_score":6}'))]);

        $result = app(AiManager::class)->scoreWritingFromImage('task', '../../.env');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['transcript']);
        Http::assertNothingSent();
    }

    public function test_an_unreadable_photo_scores_zero_and_says_so_rather_than_guessing(): void
    {
        $relative = $this->makeImage();

        Http::fake([
            '*' => Http::response(['error' => ['message' => 'boom']], 500),
        ]);

        config(['ai.mistral.key' => '']);

        $result = app(AiManager::class)->scoreWritingFromImage('task', $relative);

        $this->assertFalse($result['ok'], 'An unread photo must not be given an invented band.');
        $this->assertSame(0.0, (float) $result['band_score']);
        $this->assertNull($result['transcript']);
        $this->assertNotEmpty($result['notice']);
        $this->assertNotEmpty($result['overall_feedback']);
    }

    public function test_a_vision_unsupported_environment_tells_the_student_to_type_instead(): void
    {
        $relative = $this->makeImage();

        config(['ai.gemini.key' => '']);

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{}']]]])]);

        $result = app(AiManager::class)->scoreWritingFromImage('task', $relative);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('type your letter', (string) $result['notice']);
    }
}
