<?php

namespace Tests\Unit;

use App\Services\Ai\AiManager;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The English → Myanmar translator is the one AI helper that is easy to forget
 * when a track is added, because it lives in a different part of the facade from
 * the generator and it does not mention the track in its name.
 *
 * It used to hard-code a radiography persona AND a positive instruction to keep
 * "X-ray, CT, MRI, ALARA, PACS" in English. A positive instruction is followed
 * even when the source text never mentioned imaging, so every General lesson
 * translated through the admin panel came back with radiography vocabulary
 * grafted onto it — silently undoing the whole point of the General track.
 *
 * These tests capture the real outbound prompt and pin the persona to the track.
 */
class TranslateTrackPersonaTest extends TestCase
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
            'ai.mistral.key' => null,
            'ai.max_retries' => 0,
            'ai.retry_base_ms' => 1,
            'ai.total_budget_seconds' => 10,
            'ai.min_attempt_seconds' => 1,
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content'      => ['parts' => [['text' => '{"summary_my":"မြန်မာ"}']]],
                    'finishReason' => 'STOP',
                ]],
            ], 200),
        ]);
    }

    /** The single prompt Gemini was handed during the test. */
    private function capturedPrompt(): string
    {
        $prompts = [];

        Http::assertSent(function ($request) use (&$prompts) {
            $prompts[] = (string) ($request->data()['contents'][0]['parts'][0]['text'] ?? '');

            return true;
        });

        $this->assertNotEmpty($prompts, 'No request reached the provider.');

        return implode("\n", $prompts);
    }

    private function translate(string $track): array
    {
        /** @var AiManager $ai */
        $ai = app(AiManager::class);

        return $ai->translateToMyanmar(['summary_my' => 'A short summary.'], null, 'translate_my', $track);
    }

    public function test_the_general_track_gets_a_profession_neutral_translator_persona(): void
    {
        $out = $this->translate('General');

        $this->assertSame('မြန်မာ', $out['summary_my'] ?? null);

        $prompt = $this->capturedPrompt();

        $this->assertStringContainsString('shared OET paper', $prompt);

        // Nothing radiography-flavoured may survive anywhere in the prompt.
        $this->assertStringNotContainsString('radiography', $prompt);
        $this->assertStringNotContainsString('radiographer', $prompt);

        // The abbreviation list is the dangerous part: it is an instruction the
        // model obeys even when the source text is neutral.
        $this->assertStringContainsString('BP', $prompt);
        $this->assertStringNotContainsString('ALARA', $prompt);
        $this->assertStringNotContainsString('PACS', $prompt);

        // And the guard that stops the model importing another profession's terms.
        $this->assertStringContainsString('Do not introduce terminology', $prompt);
    }

    public function test_the_radiography_track_keeps_its_own_persona(): void
    {
        $this->translate('Radiography');

        $prompt = $this->capturedPrompt();

        $this->assertStringContainsString('Myanmar radiography students', $prompt);
        $this->assertStringContainsString('ALARA', $prompt);
        $this->assertStringContainsString('PACS', $prompt);
        $this->assertStringNotContainsString('shared OET paper', $prompt);
    }

    /**
     * The default has to stay radiography: every call site written before the
     * tracks existed omits the argument, and those all translate radiography
     * content.
     */
    public function test_the_default_track_is_radiography(): void
    {
        /** @var AiManager $ai */
        $ai = app(AiManager::class);
        $ai->translateToMyanmar(['summary_my' => 'A short summary.'], null, 'translate_my');

        $this->assertStringContainsString('Myanmar radiography students', $this->capturedPrompt());
    }

    /** An unknown track must not produce a prompt with no persona at all. */
    public function test_an_unknown_track_falls_back_to_the_radiography_persona(): void
    {
        $this->translate('Nursing');

        $prompt = $this->capturedPrompt();

        $this->assertStringContainsString('Myanmar radiography students', $prompt);
    }

    public function test_an_empty_payload_never_calls_a_provider(): void
    {
        /** @var AiManager $ai */
        $ai = app(AiManager::class);

        $this->assertSame([], $ai->translateToMyanmar(['summary_my' => '   '], null, 'translate_my', 'General'));

        Http::assertNothingSent();
    }
}
