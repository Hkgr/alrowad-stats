<?php

namespace App\Support;

use Normalizer;

/**
 * Name helpers for matching project names written in slightly different ways.
 *
 * normalize() is for comparison only (never shown); display() is the clean label we store.
 * Matching on normalized names is exact: two different normalized names are never merged.
 */
final class ArabicName
{
    /** Comparison key: no diacritics/tatweel, unified letter forms, no leading "مشروع", tidy spacing. */
    public static function normalize(string $name): string
    {
        $text = class_exists(Normalizer::class) ? (Normalizer::normalize($name, Normalizer::FORM_KC) ?: $name) : $name;

        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
        ]);
        $text = preg_replace('/\s+/u', ' ', trim($text));
        $text = preg_replace('/^مشروع\s+/u', '', $text);
        // "لعبة و فرحة" and "لعبة وفرحة" are the same name.
        $text = preg_replace('/(^|\s)و\s+/u', '$1و', $text);
        $text = preg_replace('/\s*-\s*/u', '-', $text);

        return mb_strtolower(trim($text));
    }

    /** Clean display label: trimmed, single spaces, without the generic "مشروع" prefix. */
    public static function display(string $name): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($name));

        return preg_replace('/^مشروع\s+/u', '', $text);
    }
}
