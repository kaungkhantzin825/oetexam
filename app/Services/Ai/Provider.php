<?php

namespace App\Services\Ai;

/**
 * A provider is a transport for one AI vendor. It knows nothing about OET —
 * all domain prompts live in AiManager. Both providers speak the same error
 * vocabulary (AiException) so the manager can treat them identically.
 */
interface Provider
{
    /** Machine name, used as the prefix in `ai_logs.model` (e.g. "gemini"). */
    public function name(): string;

    /** True when a usable key exists and the AI feature flag is on. */
    public function isConfigured(): bool;

    /** Ordered, de-duplicated list of models to try. */
    public function modelChain(): array;

    /**
     * True when this provider can read an image (an `attachments` opt).
     *
     * The manager uses this to route a vision request away from a text-only
     * provider, so a photographed answer is never silently handed to a
     * transport that would ignore the picture and mark an empty response.
     */
    public function supportsVision(): bool;

    /**
     * Run a prompt through the model chain.
     *
     * @param  array{feature?: string, userId?: int|null, json?: bool, attachments?: array<int, array{mime: string, data: string}>}  $opts
     * @return array{data: mixed, model: string}
     *
     * @throws AiException
     */
    public function run(string $prompt, array $opts = []): array;
}
