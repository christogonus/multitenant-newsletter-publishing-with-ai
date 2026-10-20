<?php

namespace App\Ai;

/**
 * House style for everything the AI writes: the rules every writing agent is given, and a clean-up pass
 * that removes the typographic tells (em dashes, curly quotes, ellipsis characters) a model sometimes keeps anyway.
 */
class WritingStyle
{
    /**
     * Appended to every writing agent's instructions.
     */
    public static function rules(): string
    {
        return implode("\n", [
            'Writing style (always follow):',
            '- Write like a thoughtful human editor, not an AI assistant.',
            '- Never use em dashes or en dashes. Use a comma, full stop, colon or brackets instead. A plain hyphen is fine inside words like "long-term".',
            '- Use plain straight quotes and apostrophes, three dots instead of the ellipsis character, and no emojis, arrows or decorative symbols.',
            '- Never open with stock phrases such as "In today\'s fast-paced world", "In the ever-evolving world of", "Let\'s dive in", "Picture this", "Imagine this", "Have you ever wondered", "Great question", "Certainly" or "Absolutely".',
            '- Avoid AI filler words: delve, dive into, tapestry, landscape, realm, navigate, unlock, unleash, elevate, harness, leverage, seamless, robust, game-changer, cutting-edge, pivotal, crucial, paramount, testament, embark, journey, ever-evolving.',
            '- Avoid formulaic patterns: "It\'s not just X, it\'s Y", "Whether you\'re X or Y", "It\'s worth noting", "In conclusion", rhetorical-question openers, lists of three adjectives, and endings that repeat what was already said.',
            '- Vary sentence length. Prefer concrete names, numbers and examples to general claims.',
        ]);
    }

    /**
     * Swaps the characters that give AI writing away for plain ones. Line breaks are kept.
     */
    public static function clean(string $text): string
    {
        $text = strtr($text, [
            '‘' => "'", '’' => "'", '“' => '"', '”' => '"',
            '…' => '...',
            "\u{00A0}" => ' ', "\u{200B}" => '', "\u{200C}" => '', "\u{200D}" => '', "\u{FEFF}" => '',
        ]);

        // Number ranges keep a hyphen (9–5, 2024–2025); any other dash becomes a comma.
        $text = (string) preg_replace('/(\d)[ \t]*[–—][ \t]*(\d)/u', '$1-$2', $text);
        $text = (string) preg_replace('/[ \t]*[—–―][ \t]*/u', ', ', $text);
        $text = (string) preg_replace('/^, /mu', '', $text);
        $text = (string) preg_replace('/,[ \t]*([,.;:!?])/u', '$1', $text);

        return $text;
    }

    /**
     * Cleans every string in a structured response, however deeply nested.
     *
     * @param  array<array-key, mixed>  $output
     * @return array<array-key, mixed>
     */
    public static function cleanAll(array $output): array
    {
        array_walk_recursive($output, function (mixed &$value): void {
            if (is_string($value)) {
                $value = self::clean($value);
            }
        });

        return $output;
    }
}
