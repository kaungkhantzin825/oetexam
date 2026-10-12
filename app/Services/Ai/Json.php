<?php

namespace App\Services\Ai;

/**
 * Tolerant JSON parsing for LLM output — port of backend/src/utils/json.js.
 *
 * Every provider shares this, so the repair logic lives in exactly one place.
 * Models are asked for strict JSON via their native JSON modes, but that is
 * best-effort: responses still arrive wrapped in ``` fences, prefixed with a
 * sentence of prose, or truncated mid-string when the token budget runs out.
 */
class Json
{
    /**
     * JSON decode that reports failure instead of returning null ambiguously.
     *
     * @return array{0: bool, 1: mixed} [succeeded, value]
     */
    public static function tryParse(string $s): array
    {
        $value = json_decode($s, true);

        return json_last_error() === JSON_ERROR_NONE ? [true, $value] : [false, null];
    }

    /**
     * Extract the first JSON object/array from a model response.
     *
     * Order matters: raw text first, then the brace-extracted slice, and only
     * then a truncation repair — repairing the *original* text, because slicing
     * to the last brace can silently discard recoverable content.
     *
     * @throws AiException when the text is empty or beyond saving
     */
    public static function parseLoose(?string $text)
    {
        if ($text === null || trim($text) === '') {
            throw new AiException('Empty AI response', AiException::TRUNCATED);
        }

        $cleaned = trim($text);

        // Strip markdown code fences — whether or not they wrap the whole string.
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $cleaned, $m)) {
            $cleaned = trim($m[1]);
        } else {
            $cleaned = preg_replace('/^```(?:json)?/i', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/```$/i', '', $cleaned) ?? $cleaned;
            $cleaned = trim($cleaned);
        }

        // 1) The whole string is valid JSON (the normal path).
        [$ok, $direct] = self::tryParse($cleaned);
        if ($ok) {
            return $direct;
        }

        // 2) Pull out the outermost {...} or [...] block (handles prose wrappers).
        $firstBrace = self::firstBracePos($cleaned);
        $lastBrace = max(
            ($p = strrpos($cleaned, '}')) === false ? -1 : $p,
            ($p = strrpos($cleaned, ']')) === false ? -1 : $p
        );
        $hasBlock = $firstBrace !== -1 && $lastBrace > $firstBrace;

        if ($hasBlock) {
            [$ok, $viaSlice] = self::tryParse(substr($cleaned, $firstBrace, $lastBrace - $firstBrace + 1));
            if ($ok) {
                return $viaSlice;
            }
        }

        // 3) Truncated response: repair from the first brace onwards.
        $fromStart = $firstBrace !== -1 ? substr($cleaned, $firstBrace) : $cleaned;
        $repaired = self::attemptRepair($fromStart);
        if ($repaired !== null) {
            return $repaired;
        }

        // 4) Truncated *and* wrapped in prose: repair the extracted block.
        if ($hasBlock) {
            $repaired2 = self::attemptRepair(substr($cleaned, $firstBrace, $lastBrace - $firstBrace + 1));
            if ($repaired2 !== null) {
                return $repaired2;
            }
        }

        throw new AiException('Could not parse the AI response as JSON', AiException::TRUNCATED);
    }

    private static function firstBracePos(string $s): int
    {
        $a = strpos($s, '{');
        $b = strpos($s, '[');

        if ($a === false) {
            return $b === false ? -1 : $b;
        }
        if ($b === false) {
            return $a;
        }

        return min($a, $b);
    }

    /**
     * Best-effort repair of a truncated JSON document: closes an unterminated
     * string, drops a dangling comma, and appends the missing closing brackets.
     *
     * @return mixed|null the parsed value, or null if beyond saving
     */
    public static function attemptRepair(string $s)
    {
        $inString = false;
        $escaped = false;
        $open = [];
        $len = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($c === '\\') {
                    $escaped = true;
                } elseif ($c === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($c === '"') {
                $inString = true;
            } elseif ($c === '{') {
                $open[] = '}';
            } elseif ($c === '[') {
                $open[] = ']';
            } elseif ($c === '}' || $c === ']') {
                array_pop($open);
            }
        }

        $out = $s;
        if ($inString) {
            $out .= '"';
        }
        $out = preg_replace('/,\s*$/', '', $out) ?? $out;
        $out = preg_replace('/:\s*$/', ': null', $out) ?? $out;
        while (! empty($open)) {
            $out .= array_pop($open);
        }

        [$ok, $value] = self::tryParse($out);

        return $ok ? $value : null;
    }
}
