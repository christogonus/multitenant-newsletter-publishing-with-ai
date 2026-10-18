<?php

namespace App\Support;

use App\Enums\BlockType;
use Illuminate\Support\Str;

/**
 * Issue content is a list of typed blocks, never raw HTML. This class is the only gate blocks pass through
 * before they are stored, so every block that reaches a template has a known shape and a safe URL.
 */
class Blocks
{
    /**
     * @param  array<int, mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $blocks): array
    {
        $normalized = [];

        foreach (array_slice(array_values($blocks), 0, 120) as $block) {
            if (! is_array($block) || ! ($type = BlockType::tryFrom((string) ($block['type'] ?? '')))) {
                continue;
            }

            $clean = self::clean($type, $block);

            if ($clean !== null) {
                $normalized[] = ['id' => self::id($block), 'type' => $type->value, ...$clean];
            }
        }

        return $normalized;
    }

    /**
     * A blank block of the given type, ready for the editor.
     *
     * @return array<string, mixed>
     */
    public static function blank(BlockType $type): array
    {
        return [
            'id' => (string) Str::ulid(),
            'type' => $type->value,
            ...match ($type) {
                BlockType::Heading, BlockType::Paragraph => ['text' => ''],
                BlockType::Bullets => ['items' => ['']],
                BlockType::Link => ['text' => '', 'url' => '', 'description' => ''],
                BlockType::Image => ['url' => '', 'alt' => '', 'caption' => ''],
                BlockType::Quote => ['text' => '', 'cite' => ''],
                BlockType::Divider => [],
            },
        ];
    }

    /**
     * Only http(s) URLs survive; anything else (javascript:, data:, relative) becomes empty.
     */
    public static function safeUrl(mixed $url): string
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : '';
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private static function clean(BlockType $type, array $block): ?array
    {
        $text = fn (string $key, int $max) => Str::limit(trim(strip_tags((string) ($block[$key] ?? ''))), $max, '');

        return match ($type) {
            BlockType::Heading => ['text' => $text('text', 200)],
            BlockType::Paragraph => ['text' => $text('text', 6000)],
            BlockType::Quote => ['text' => $text('text', 1000), 'cite' => $text('cite', 200)],
            BlockType::Bullets => ['items' => array_values(array_map(
                fn ($item) => Str::limit(trim(strip_tags((string) $item)), 500, ''),
                array_slice(array_filter((array) ($block['items'] ?? []), 'is_scalar'), 0, 25),
            ))],
            BlockType::Link => [
                'text' => $text('text', 200),
                'url' => self::safeUrl($block['url'] ?? ''),
                'description' => $text('description', 500),
            ],
            BlockType::Image => [
                'url' => self::safeUrl($block['url'] ?? ''),
                'alt' => $text('alt', 300),
                'caption' => $text('caption', 300),
            ],
            BlockType::Divider => [],
        };
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function id(array $block): string
    {
        $id = (string) ($block['id'] ?? '');

        return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) ? $id : (string) Str::ulid();
    }
}
