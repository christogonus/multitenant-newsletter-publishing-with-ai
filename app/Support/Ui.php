<?php

namespace App\Support;

use App\Enums\Area;
use Illuminate\Support\Facades\File;

/**
 * Class maps for the component kit. Full class names live here so Tailwind can see them.
 */
class Ui
{
    /** @var array<string, string> */
    private static array $icons = [];

    public static function solid(Area|string|null $area): string
    {
        return match (self::key($area)) {
            'create' => 'bg-create hover:bg-create-hover text-white focus-visible:outline-create',
            'publish' => 'bg-publish hover:bg-publish-hover text-white focus-visible:outline-publish',
            'grow' => 'bg-grow hover:bg-grow-hover text-white focus-visible:outline-grow',
            'earn' => 'bg-earn hover:bg-earn-hover text-white focus-visible:outline-earn',
            'social' => 'bg-social hover:bg-social-hover text-white focus-visible:outline-social',
            'automate' => 'bg-automate hover:bg-automate-hover text-white focus-visible:outline-automate',
            'danger' => 'bg-danger hover:bg-danger-hover text-white focus-visible:outline-danger',
            default => 'bg-brand hover:bg-brand-hover text-white focus-visible:outline-brand',
        };
    }

    public static function tint(Area|string|null $area): string
    {
        return match (self::key($area)) {
            'create' => 'bg-create-tint text-create-ink',
            'publish' => 'bg-publish-tint text-publish-ink',
            'grow' => 'bg-grow-tint text-grow-ink',
            'earn' => 'bg-earn-tint text-earn-ink',
            'social' => 'bg-social-tint text-social-ink',
            'automate' => 'bg-automate-tint text-automate-ink',
            'success' => 'bg-success-tint text-success-ink',
            'warning' => 'bg-warning-tint text-warning-ink',
            'danger' => 'bg-danger-tint text-danger-ink',
            'neutral' => 'bg-subtle text-muted',
            default => 'bg-brand-tint text-brand-ink',
        };
    }

    public static function dot(Area|string|null $tone): string
    {
        return match (self::key($tone)) {
            'success' => 'bg-success',
            'warning' => 'bg-warning',
            'danger' => 'bg-danger',
            'neutral' => 'bg-muted',
            'create' => 'bg-create',
            'publish' => 'bg-publish',
            'grow' => 'bg-grow',
            'earn' => 'bg-earn',
            'social' => 'bg-social',
            'automate' => 'bg-automate',
            default => 'bg-brand',
        };
    }

    public static function border(Area|string|null $area): string
    {
        return match (self::key($area)) {
            'create' => 'border-create',
            'publish' => 'border-publish',
            'grow' => 'border-grow',
            'earn' => 'border-earn',
            'social' => 'border-social',
            'automate' => 'border-automate',
            default => 'border-brand',
        };
    }

    public static function bar(Area|string|null $area): string
    {
        return match (self::key($area)) {
            'create' => 'bg-create',
            'publish' => 'bg-publish',
            'grow' => 'bg-grow',
            'earn' => 'bg-earn',
            'social' => 'bg-social',
            'automate' => 'bg-automate',
            'success' => 'bg-success',
            default => 'bg-brand',
        };
    }

    /**
     * Inline SVG paths for an icon kept in resources/icons.
     */
    public static function icon(string $name): string
    {
        if (! preg_match('/^[a-z0-9-]+$/', $name)) {
            return '';
        }

        return self::$icons[$name] ??= File::exists($path = resource_path("icons/{$name}.svg"))
            ? File::get($path)
            : '';
    }

    private static function key(Area|string|null $value): ?string
    {
        return $value instanceof Area ? $value->value : $value;
    }
}
