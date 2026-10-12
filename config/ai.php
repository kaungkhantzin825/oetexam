<?php

/**
 * AI provider configuration.
 *
 * The keys live ONLY here on the server. They are never returned to the client
 * and never bundled into frontend code.
 */
return [

    /** Master switch — set AI_ENABLED=false to disable every provider at once. */
    'enabled' => filter_var(env('AI_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * ---------------------------------------------------------------------
     * Wall-clock budget
     * ---------------------------------------------------------------------
     * The chain is providers x models x (1 + retries). Unbounded, that can
     * outlive PHP's execution limit, and the request dies with a fatal error
     * instead of the classified AiException the callers are written to handle.
     *
     * So every attempt in one AI request shares a single deadline: each HTTP
     * call is capped by whatever is left of `total_budget_seconds`, and no new
     * attempt starts with less than `min_attempt_seconds` on the clock. The
     * worst case is therefore ~`total_budget_seconds`, regardless of how many
     * models are configured.
     *
     * Keep `total_budget_seconds` comfortably below the SAPI's
     * `max_execution_time` (60s on the stock php.ini). The AI controllers also
     * raise that limit with set_time_limit() for headroom.
     */
    'total_budget_seconds' => (int) env('AI_TOTAL_BUDGET_SECONDS', 45),
    'min_attempt_seconds'  => (int) env('AI_MIN_ATTEMPT_SECONDS', 5),

    /** Retries for transient failures (per-minute 429, 5xx) before moving on. */
    'max_retries'   => (int) env('AI_MAX_RETRIES', 1),
    'retry_base_ms' => (int) env('AI_RETRY_BASE_MS', 800),

    /*
     * ---------------------------------------------------------------------
     * Photographed Writing answers
     * ---------------------------------------------------------------------
     * A candidate may hand-write the letter, photograph it and upload the
     * photo instead of typing. The image goes to Gemini as inline base64,
     * which inflates it by ~33%, and Google caps inline data at 20 MB — so
     * keep the ceiling well under that. 8 MB is a modern phone photo after
     * the client-side downscale and is generous for handwriting.
     */
    'writing_image' => [
        'max_kb' => (int) env('OET_WRITING_IMAGE_MAX_KB', 8192),
    ],

    'gemini' => [
        'key'  => env('GEMINI_API_KEY', ''),
        'base' => env('GEMINI_API_BASE', 'https://generativelanguage.googleapis.com'),

        /*
         * The free tier meters quota PER MODEL, PER DAY, so an ordered chain
         * keeps AI alive all day instead of dying at request 21. `gemini-1.5-*`
         * is retired (404s) and gemini-2.5-pro / 2.5-flash-lite are not served
         * to free-tier keys.
         */
        'model'           => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GEMINI_FALLBACK_MODELS', 'gemini-3.1-flash-lite,gemini-3-flash-preview,gemini-2.5-flash'))
        ))),

        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 8192),

        /*
         * Per-call ceiling. This is an upper bound only — the shared budget
         * clamps it down to the time actually left in the request.
         */
        'timeout' => (int) env('GEMINI_TIMEOUT_SECONDS', 40),
    ],

    'mistral' => [
        'key'  => env('MISTRAL_API_KEY', ''),
        'base' => env('MISTRAL_API_BASE', 'https://api.mistral.ai/v1'),

        /*
         * Verified against this key: mistral-large-* is not on the tier
         * (403 / 1910) and mistral-medium/small are rate-limited, so we lead
         * with the models that actually answer.
         */
        'model'           => env('MISTRAL_MODEL', 'open-mistral-nemo'),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MISTRAL_FALLBACK_MODELS', 'ministral-8b-latest,ministral-3b-latest,codestral-latest'))
        ))),

        'max_output_tokens' => (int) env('MISTRAL_MAX_OUTPUT_TOKENS', 8192),
        'timeout'           => (int) env('MISTRAL_TIMEOUT_SECONDS', 40),

        /*
         * TTS is used by the Listening-audio generator. Gemini TTS allows only
         * ~10 requests/day, so Mistral is the practical fallback. `voice` is
         * REQUIRED by the Mistral speech endpoint.
         */
        'tts_model' => env('MISTRAL_TTS_MODEL', 'voxtral-mini-tts-latest'),
        'tts_voice' => env('MISTRAL_TTS_VOICE', 'gb_oliver_neutral'),
    ],

];
