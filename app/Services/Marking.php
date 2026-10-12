<?php

namespace App\Services;

/**
 * Port of backend/src/utils/helpers.js (the marking half).
 *
 * The MCQ rule is the single most important thing in this file:
 * `correct_answer` stores a 0-based option index as a string ("0" = option A).
 * Both sides of a comparison go through resolveMcqIndex(), so index-vs-index
 * and text-vs-text always agree — and an unrecognised answer returns null, so
 * it is marked WRONG rather than accidentally right.
 */
class Marking
{
    /** Trim, lowercase, collapse whitespace, drop punctuation. */
    public static function normaliseAnswer($value): string
    {
        $s = (string) ($value ?? '');
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[.,;:!?"\'`()\[\]{}]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /** Map a raw percentage (0-100) onto the OET 0.0-6.0 band scale. */
    public static function percentageToBand($percentage): float
    {
        $pct = max(0.0, min(100.0, (float) $percentage));
        $raw = ($pct / 100) * 6;

        return round($raw * 2) / 2;
    }

    /** Round to 2 decimals without floating-point noise. */
    public static function round2($n): float
    {
        return round(((float) $n) * 100) / 100;
    }

    /** Forgiving comparison so short-answer marking stays fair. */
    public static function isShortAnswerCorrect($userAnswer, $correctAnswer): bool
    {
        $a = self::normaliseAnswer($userAnswer);
        $b = self::normaliseAnswer($correctAnswer);

        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        // Accept a key phrase inside a longer sentence, e.g.
        // "the lateral meniscus" ~ "lateral meniscus".
        return str_contains($a, $b) || str_contains($b, $a);
    }

    /**
     * Resolve an MCQ answer to a 0-based option index, or null when it cannot
     * be mapped. Accepts the 0-based index ("0"), a letter ("a"), or the literal
     * option text — the stored `correct_answer` has historically used all three.
     */
    public static function resolveMcqIndex($userAnswer, $options, $correctAnswer = null): ?int
    {
        $normalised = self::normaliseAnswer($userAnswer);
        if ($normalised === '') {
            return null;
        }

        $letters = ['a', 'b', 'c', 'd', 'e', 'f'];

        // Letter form: "b" -> index 1
        $letterIdx = array_search($normalised, $letters, true);
        if ($letterIdx !== false) {
            return (int) $letterIdx;
        }

        // Numeric form: "0" -> first option (0-based, matching the student UI)
        if (preg_match('/^\d+$/', $normalised)) {
            return (int) $normalised;
        }

        // Literal option text
        if (is_array($options)) {
            foreach (array_values($options) as $i => $option) {
                if (self::normaliseAnswer($option) === $normalised) {
                    return (int) $i;
                }
            }
        }

        // Last resort: the caller passed the stored answer itself as the user
        // answer (admin tools and tests do this). Only accept an EXACT match —
        // otherwise an unrecognised answer would resolve to the correct index
        // and be marked right.
        if ($correctAnswer !== null && $correctAnswer !== '') {
            $correct = self::normaliseAnswer($correctAnswer);
            if ($normalised === $correct) {
                if (preg_match('/^\d+$/', $correct)) {
                    return (int) $correct;
                }
                $cIdx = array_search($correct, $letters, true);
                if ($cIdx !== false) {
                    return (int) $cIdx;
                }
                if (is_array($options)) {
                    foreach (array_values($options) as $i => $option) {
                        if (self::normaliseAnswer($option) === $correct) {
                            return (int) $i;
                        }
                    }
                }
            }
        }

        return null;
    }
}
