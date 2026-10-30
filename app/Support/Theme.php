<?php

namespace App\Support;

use App\Enums\Product;
use App\Models\Newsletter;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves the visual tokens for a publication: its template, plus custom branding when OTO 8 is owned.
 */
class Theme
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function templates(): array
    {
        return config('templates.templates');
    }

    public static function isPremium(string $key): bool
    {
        return (bool) (self::templates()[$key]['premium'] ?? false);
    }

    /**
     * @return array{key: string, name: string, font: string, heading_font: string, background: string, surface: string, text: string, muted: string, accent: string, header: string, radius: string, logo_url: ?string, banner_url: ?string}
     */
    public static function for(Newsletter $newsletter): array
    {
        $templates = self::templates();
        $hasPremium = $newsletter->user?->owns(Product::Oto8Premium) ?? false;

        $key = $newsletter->template;

        if (! isset($templates[$key]) || (self::isPremium($key) && ! $hasPremium)) {
            $key = 'classic';
        }

        $tokens = $templates[$key];
        $logoUrl = null;

        if ($hasPremium) {
            $brand = $newsletter->brand();
            $tokens['accent'] = self::color($brand['accent_color'] ?? null, $tokens['accent']);

            if (! empty($newsletter->branding['font']) && isset(config('templates.fonts')[$newsletter->branding['font']])) {
                $tokens['font'] = $newsletter->branding['font'];
                $tokens['heading_font'] = $newsletter->branding['font'];
            }

            if (! empty($brand['logo_path'])) {
                $logoUrl = Storage::disk('public')->url($brand['logo_path']);
            }
        }

        // The banner is for every publication, not only premium ones.
        $bannerPath = $newsletter->branding['banner_path'] ?? null;
        $fonts = config('templates.fonts');

        return [
            'key' => $key,
            'name' => $tokens['name'],
            'font' => $fonts[$tokens['font']]['stack'] ?? $fonts['system']['stack'],
            'heading_font' => $fonts[$tokens['heading_font']]['stack'] ?? $fonts['system']['stack'],
            'background' => $tokens['background'],
            'surface' => $tokens['surface'],
            'text' => $tokens['text'],
            'muted' => $tokens['muted'],
            'accent' => $tokens['accent'],
            'header' => $tokens['header'],
            'radius' => $tokens['radius'],
            'logo_url' => $logoUrl,
            'banner_url' => $bannerPath ? Storage::disk('public')->url($bannerPath) : null,
        ];
    }

    /**
     * Only six-digit hex colours are accepted, so a colour can never break out of a style attribute.
     */
    public static function color(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }

    /**
     * Readable text colour (black or white) for a given background.
     */
    public static function onColor(string $hex): string
    {
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');
        $luminance = (0.299 * $red + 0.587 * $green + 0.114 * $blue) / 255;

        return $luminance > 0.6 ? '#111111' : '#ffffff';
    }
}
